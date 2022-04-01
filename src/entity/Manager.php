<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\{Database, Result, trait\DbTrait};
use froq\database\record\Record;
use froq\validation\ValidationError;
use froq\pager\Pager;
use ItemList, ReflectionProperty;

/**
 * A class, creates & manages data entities using attributes/annotations of these entities,
 * also dial with current open database for queries, executions and transactions.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\Manager
 * @author  Kerem Güneş
 * @since   5.0
 */
final class Manager
{
    use DbTrait;

    /**
     * Constructor.
     *
     * @param  froq\database\Database|null $db
     * @throws froq\database\entity\ManagerException
     */
    public function __construct(Database $db = null)
    {
        // Try to use active app database object.
        $db ??= function_exists('app') ? app()->database() : throw new ManagerException(
            'No database given to deal, be sure running this module with `froq\app` ' .
            'module and be sure `database` option exists in app config or pass $db argument'
        );

        $this->db = $db;
    }

    /**
     * Create an entity with/without given properties.
     *
     * @param  string    $class
     * @param  mixed  ...$properties
     * @return object
     * @causes froq\database\entity\ManagerException
     */
    public function createEntity(string $class, mixed ...$properties): object
    {
        /** @var froq\database\entity\ClassMeta */
        $classMeta = $this->getClassMeta($class);

        $entity = $this->initEntity($class, $properties);
        $record = $this->initRecord($classMeta, $entity, true, true);

        $this->setProperties($entity, $record, $classMeta);
        $this->setInternalProperties($entity, $record);

        return $entity;
    }

    /**
     * Create an entity list with/without given entities.
     *
     * @param  string    $class
     * @param  object ...$entities
     * @return object
     * @causes froq\database\entity\ManagerException
     */
    public function createEntityList(string $class, object ...$entities): object
    {
        return $this->initEntityList($class, $entities);
    }

    /**
     * Save an entity.
     *
     * @param  object $entity
     * @return object
     * @causes froq\database\entity\ManagerException
     * @throws froq\validation\ValidationError
     */
    public function save(object $entity): object
    {
        /** @var froq\database\entity\ClassMeta */
        $classMeta = $this->getClassMeta($entity);

        $data = [];
        foreach ($classMeta->getProperties() as $name => $propertyMeta) {
            $data[$name] = $this->getPropertyValue($entity, $propertyMeta->getReflection())
                ?? $propertyMeta->getValidationDefault();
        }

        // Clear null values & discard validations for "update" actions only on existing records.
        // So, if any non nullable field was sent to db, an error will be raised already.
        $id = $this->getPrimaryValue($entity, $classMeta, check: false);
        if ($id !== null) {
            $data = array_clear($data, [null]);
            $validations = null;
        } else {
            $validations = true;
        }

        /** @var froq\database\record\Record */
        $record = $this->initRecord($classMeta, $entity, true, $validations);

        try {
            $record->save($data);
        } catch (ValidationError $e) {
            throw new ValidationError(
                'Cannot save entity (%s), validation failed [tip: %s]'
                [$entity::class, ValidationError::tip()], errors: $e->errors()
            );
        }

        $this->setProperties($entity, $record, $classMeta);
        $this->setInternalProperties($entity, $record, ['saved', $record->isSaved()]);

        // Call action method when provided.
        $this->callAction($entity, 'onSave');

        return $entity;
    }

    /**
     * Save an entity list.
     *
     * @param  array|froq\database\entity\EntityList $entityList
     * @param  bool                                  $init
     * @return array|froq\database\entity\EntityList
     * @causes froq\database\entity\ManagerException
     */
    public function saveAll(array|EntityList $entityList, bool $init = false): array|EntityList
    {
        foreach ($entityList as $entity) {
            $this->save($entity);
        }

        if ($init && is_array($entityList)) {
            $entityList = $this->initEntityList(null, $entityList);
        }

        return $entityList;
    }

    /**
     * Find an entity related record & fill given entity with found record data.
     *
     * @param  object $entity
     * @return object
     * @causes froq\database\entity\ManagerException
     */
    public function find(object $entity): object
    {
        /** @var froq\database\entity\ClassMeta */
        $classMeta = $this->getClassMeta($entity);

        $id = $this->getPrimaryValue($entity, $classMeta);

        /** @var froq\database\record\Record */
        $record = $this->initRecord($classMeta, $entity, true, primaryRequired: true)
            ->find($id);

        $this->setProperties($entity, $record, $classMeta);
        $this->setInternalProperties($entity, $record, ['finded', $record->isFinded()]);

        // Call action method when provided.
        $this->callAction($entity, 'onFind');

        return $entity;
    }

    /**
     * Find all entity list related records & fill given entity list with found records data.
     *
     * @param  array<object>|froq\database\entity\EntityList<object> $entityList
     * @param  bool                                                  $init
     * @return array<object>|froq\database\entity\EntityList<object>
     * @causes froq\database\entity\ManagerException
     * @throws froq\database\entity\ManagerException
     */
    public function findAll(array|EntityList $entityList, bool $init = false): array|EntityList
    {
        [$ids, $items, $primary, $classMeta] = $this->prepareListItems($entityList);

        /** @var froq\database\record\RecordList */
        $records = $this->initRecord($classMeta, $items[0], true)
            ->findAll($ids, limit: $items->count());

        if ($records->count()) {
            // For proper index selection below (null-row safety).
            $this->sortListItemsByPrimary($records, $items, $primary);

            foreach ($records as $i => $record) {
                $entity = $items[$i];

                $this->setProperties($entity, $record, $classMeta);
                $this->setInternalProperties($entity, $record, ['finded', true]);

                // Call action method when provided.
                $this->callAction($entity, 'onFind');
            }
        }

        if ($init && is_array($entityList)) {
            $entityList = $this->initEntityList(null, $entityList);
        }

        return $entityList;
    }

    /**
     * Find all entity records by given conditions or using given entity properties as condition
     * & init/fill given entity/entity class with found records data when db supports, returning
     * an entity list.
     *
     * Note: To prevent all of given entity object properties be used as search parameters, an
     * empty entity object or entity class can be given.
     *
     * When pagination desired, following example can be used.
     *
     * ```
     * $page  = Get from somewhere, eg. $_GET['page'].
     * $limit = Get from somewhere or set as constant, default is 10.
     * $count = Count of total records by some conditions. <optional>
     *
     * $pager = new Pager(['page' => $page, 'limit' => $limit, 'count' => $count])
     * $pager->run() <optional>
     *
     * $result = $em->findBy($entityObject or $entityClass, pager: $pager, ...<other params>)
     *```
     *
     * @param  object|string          $entity
     * @param  string|array|null      $where
     * @param  array|null             $params Used only when $where is string.
     * @param  string|null            $order
     * @param  int|null               $limit
     * @param  int|null               $offset
     * @param  froq\pager\Pager|null &$pager
     * @return froq\database\entity\EntityList
     * @causes froq\database\entity\ManagerException
     */
    public function findBy(object|string $entity, string|array $where = null, array $params = null,
        string $order = null, int $limit = null, int $offset = null, Pager &$pager = null): EntityList
    {
        // When no entity instance given.
        is_string($entity) && $entity = new $entity();

        /** @var froq\database\entity\ClassMeta */
        $classMeta = $this->getClassMeta($entity);

        $this->prepareWhere($entity, $classMeta, $where, $params);

        /** @var froq\database\record\Record */
        $record = $this->initRecord($classMeta, $entity, true);

        if ($pager) {
            // Disable redirects.
            $pager->redirect = false;

            // Pager was run?
            if ($pager->totalPages === null) {
                $count = $pager->totalRecords ?? $record->count($where, $params);
                [$limit, $offset] = $pager->run($count);
            } else {
                [$limit, $offset] = [$pager->limit, $pager->offset];
            }
        }

        // Order by clause, default is primary.
        $order ??= $classMeta->getTablePrimary();

        /** @var froq\database\record\RecordList */
        $records = $record->findBy($where, $params, limit: $limit, offset: $offset, order: $order, fetch: 'array');

        $entityList = $this->initEntityList($classMeta->getListClass());

        if ($records->count()) {
            foreach ($records as $record) {
                $entityClone = clone $entity;

                $this->setProperties($entityClone, $record, $classMeta);
                $this->setInternalProperties($entityClone, $record, ['finded', true]);

                // Call action method when provided.
                $this->callAction($entityClone, 'onFind');

                $entityClones[] = $entityClone;
            }

            // Fill entity list & set pager.
            $entityList->fill(...$entityClones);
            $pager && $entityList->setPager($pager);
        }

        return $entityList;
    }

    /**
     * Remove an entity related record & fill given entity with found record data.
     *
     * @param  object $entity
     * @return object
     * @causes froq\database\entity\ManagerException
     */
    public function remove(object $entity): object
    {
        /** @var froq\database\entity\ClassMeta */
        $classMeta = $this->getClassMeta($entity);

        $id = $this->getPrimaryValue($entity, $classMeta);

        /** @var froq\database\record\Record */
        $record = $this->initRecord($classMeta, $entity, true, primaryRequired: true)
            ->remove($id);

        $this->setProperties($entity, $record, $classMeta);
        $this->setInternalProperties($entity, $record, ['removed', $record->isRemoved()]);

        // Call action method when provided.
        $this->callAction($entity, 'onRemove');

        return $entity;
    }

    /**
     * Remove all entity list related records & fill given entity list with removed records data.
     *
     * @param  array|froq\database\entity\EntityList $entityList
     * @param  bool                                  $init
     * @return array|froq\database\entity\EntityList
     * @causes froq\database\entity\ManagerException
     */
    public function removeAll(array|EntityList $entityList, bool $init = false): array|EntityList
    {
        [$ids, $items, $primary, $classMeta] = $this->prepareListItems($entityList);

        /** @var froq\database\record\RecordList */
        $records = $this->initRecord($classMeta, $items[0], true)
            ->removeAll($ids);

        if ($records->count()) {
            // For proper index selection below (null-row safety).
            $this->sortListItemsByPrimary($records, $items, $primary);

            foreach ($records as $i => $record) {
                $entity = $items[$i];

                $this->setProperties($entity, $record, $classMeta);
                $this->setInternalProperties($entity, $record, ['removed', true]);

                // Call action method when provided.
                $this->callAction($entity, 'onRemove');
            }
        }

        if ($init && is_array($entityList)) {
            $entityList = $this->initEntityList(null, $entityList);
        }

        return $entityList;
    }

    /**
     * Remove all entity records by given conditions or using given entity properties as condition
     * & init/fill given entity class with removed records data when db supports, returning an entity
     * list.
     *
     * @param  string|object     $entity
     * @param  string|array|null $where
     * @param  array|null        $params Used only when $where is string.
     * @return froq\database\entity\EntityList
     * @causes froq\database\entity\ManagerException
     */
    public function removeBy(string|object $entity, string|array $where = null, array $params = null): EntityList
    {
        // When no entity instance given.
        is_string($entity) && $entity = new $entity();

        /** @var froq\database\entity\ClassMeta */
        $classMeta = $this->getClassMeta($entity);

        $this->prepareWhere($entity, $classMeta, $where, $params);

        /** @var froq\database\record\RecordList */
        $records = $this->initRecord($classMeta, $entity, true)
            ->removeBy($where, $params);

        $entityList = $this->initEntityList($classMeta->getListClass());

        if ($records->count()) {
            foreach ($records as $record) {
                $entityClone = clone $entity;

                $this->setProperties($entityClone, $record, $classMeta);
                $this->setInternalProperties($entityClone, $record, ['removed', true]);

                // Call action method when provided.
                $this->callAction($entityClone, 'onRemove');

                $entityClones[] = $entityClone;
            }

            // Fill entity list.
            $entityList->fill(...$entityClones);
        }

        return $entityList;
    }

    /**
     * Init a record by given entity class meta.
     */
    private function initRecord(ClassMeta $classMeta, object $entity = null, bool $fields = null, bool $validations = null,
        bool $primaryRequired = false): Record
    {
        // Use annotated record class or default.
        $record = $classMeta->getRecordClass() ?: Record::class;

        // Used for only "find/remove" actions.
        $fields && $fields = $this->getFields($entity, $classMeta);

        // Used for only "save" actions.
        $validations && $validations = $this->getValidations($entity, $classMeta);

        [$table, $tablePrimary] = $classMeta->packTableStuff();

        if (!$table) {
            throw new ManagerException('Entity %s has no table definition',
                $classMeta->getName());
        }
        if ($primaryRequired && !$tablePrimary) {
            throw new ManagerException('Entity %s has no primary (id) definition',
                $classMeta->getName());
        }

        $record = new $record(
            $this->db, $table, $tablePrimary,
            validations: $validations, options: [
                'transaction' => $classMeta->getOption('transaction', default: true),
                'sequence'    => $classMeta->getOption('sequence',    default: !!$tablePrimary),
                'validate'    => $classMeta->getOption('validate',    default: !!$validations),
            ]
        );

        $fields && $record->return($fields);

        return $record;
    }

    /**
     * Init an entity with/without given class & with/without given properties.
     */
    private function initEntity(string|null $class, array $properties = null): Entity
    {
        // Check class validity.
        if ($class) {
            class_exists($class) || throw new ManagerException(
                'Entity class `%s` not exists', $class
            );
            class_extends($class, Entity::class) || throw new ManagerException(
                'Entity class `%s` must extend `%s`', [$class, Entity::class]
            );

            $entity = new $class();
        } else {
            $entity = new class() extends Entity {};
        }

        // Set manager & properties.
        $entity->setManager($this);
        $properties && $entity->fill(...$properties);

        return $entity;
    }

    /**
     * Init an entity list with/without given class & with/without given entities.
     */
    private function initEntityList(string|null $class, array $entities = null): EntityList
    {
        // Check class validity.
        if ($class) {
            class_exists($class) || throw new ManagerException(
                'Entity list class `%s` not exists', $class
            );
            class_extends($class, EntityList::class) || throw new ManagerException(
                'Entity list class `%s` must extend `%s`', [$class, EntityList::class]
            );

            $entityList = new $class();
        } else {
            $entityList = new class() extends EntityList {};
        }

        // Set manager & stack items.
        $entityList->setManager($this);
        $entities && $entityList->fill(...$entities);

        return $entityList;
    }

    /**
     * Get class meta parsing given entity meta attributes/annotations or throw a
     * `ManagerException` if given entity has no meta attributes/annotations.
     */
    private function getClassMeta(string|object $entity): ClassMeta
    {
        return MetaParser::parseClassMeta($entity)
            ?: throw new ManagerException('No meta in class ' . get_class_name($entity));
    }

    /**
     * Set entity properties.
     */
    private function setProperties(object $entity, array|Record $record, ClassMeta $classMeta): void
    {
        $data = is_array($record) ? $record : $record->toArray();
        if ($data) {
            $properties = $classMeta->getProperties();
            foreach ($data as $name => $value) {
                // Set present (defined/parsed) properties only.
                if (isset($properties[$name])) {
                    $this->setPropertyValue($entity, $properties[$name]->getReflection(), $value);
                }
            }
        }
    }

    /**
     * Set entity internal properties.
     */
    private function setInternalProperties(object $entity, Record $record, array $state = null): void
    {
        // When entity extends Entity.
        if ($entity instanceof Entity) {
            $entity->setManager($this);

            // Set result state.
            $state && $entity->setState(...$state);
        }
    }

    /**
     * Get an entity fields when defined `FIELDS` constant or `fields()` method
     * on entity class, or get them from class meta or return `*` (all) as default.
     */
    private function getFields(object|null $entity, ClassMeta $classMeta): array|string
    {
        $ret = $def = null;
        $ref = $classMeta->getReflection();

        if ($entity) {
            // When "FIELDS" constant is defined on entity class.
            if ($ref->hasConstant('FIELDS')) {
                $ret = $entity::FIELDS;
                $def = 1;
            }
            // When "fields()" method exists on entity class.
            elseif ($ref->hasMethod('fields')) {
                $ret = $entity->fields();
                $def = 2;
            }

            if ($def && !is_type_of($ret, 'array|string')) {
                $message = ($def == 1)
                    ? 'Constant %s::FIELDS must define array, %t defined'
                    : 'Method %s::fields() must return array, %t returned';
                throw new ManagerException($message, [$entity::class, $ret]);
            }
        }

        // When properties have "validation" meta on entity class.
        if (!$def) {
            foreach ($classMeta->getProperties() as $name => $propertyMeta) {
                // Skip entity properties.
                $propertyMeta->hasEntity() || $ret[] = $name;
            }
        }

        // Prevent weird issues.
        if (!$ret || $ret === ['*']) {
            $ret = '*';
        }

        return $ret;
    }

    /**
     * Get an entity validations when defined `VALIDATIONS` constant or `validations()`
     * method on entity class, or get them from class meta or return `null`.
     */
    private function getValidations(object|null $entity, ClassMeta $classMeta): array|null
    {
        $ret = $def = null;
        $ref = $classMeta->getReflection();

        if ($entity) {
            // When "VALIDATIONS" constant is defined on entity class.
            if ($ref->hasConstant('VALIDATIONS')) {
                $ret = $entity::VALIDATIONS;
                $def = 1;
            }
            // When "validations()" method exists on entity class.
            elseif ($ref->hasMethod('validations')) {
                $ret = $entity->validations();
                $def = 2;
            }

            if ($def && !is_type_of($ret, 'array')) {
                $message = ($def == 1)
                    ? 'Constant %s::VALIDATIONS must define array, %t defined'
                    : 'Method %s::validations() must return array, %t returned';
                throw new ManagerException($message, [$entity::class, $ret]);
            }
        }

        // When properties have "validation" meta on entity class.
        if (!$def) {
            foreach ($classMeta->getProperties() as $name => $propertyMeta) {
                // Skip entity properties.
                $propertyMeta->hasEntity() || $ret[$name] = $propertyMeta->getValidation();
            }
        }

        return $ret;
    }

    /**
     * Get an entity primary value using given entity class meta when available.
     */
    private function getPrimaryValue(object $entity, ClassMeta $classMeta, bool $check = true): int|string|null
    {
        $primary = (string) $classMeta->getTablePrimary();

        if ($primary) {
            $value = $this->getPropertyValue($entity, $classMeta->getProperty($primary)->getReflection());
            if ($check && !is_type_of($value, 'int|string')) {
                throw new ManagerException(
                    'Primary (%s::$%s) value must be int|string, %t given',
                    [$entity::class, $primary, $value]
                );
            }
        }

        return $value ?? null;
    }

    /**
     * Set an entity property value.
     */
    private function setPropertyValue(object $entity, ReflectionProperty $property, mixed $value): void
    {
        // When property-specific setter is available.
        if (method_exists($entity, ($method = ('set' . $property->name)))) {
            $entity->$method($value);
            return;
        }

        $property->setValue($entity, $value);
    }

    /**
     * Get an entity property value.
     */
    private function getPropertyValue(object $entity, ReflectionProperty $property): mixed
    {
        // When property-specific getter is available.
        if (method_exists($entity, ($method = ('get' . $property->name)))) {
            return $entity->$method();
        }

        return $property->getValue($entity);
    }

    /**
     * Prepare given where condition appending given entity properties.
     */
    private function prepareWhere(object $entity, ClassMeta $classMeta, string|array|null &$where, array|null &$params): void
    {
        $where ??= [];

        if (is_string($where)) {
            $where  = trim($where);
            $params = (array) $params;
        } else {
            foreach ($classMeta->getProperties() as $name => $propertyMeta) {
                // When "where" does not contains a condition already.
                if (!array_key_exists($name, $where)) {
                    $value = $this->getPropertyValue($entity, $propertyMeta->getReflection());
                    // Skip nulls.
                    if ($value !== null) {
                        $where[$name] = $value;
                    }
                }
            }
        }
    }

    /**
     * Prepare list items for `findAll()` & `removeAll()` methods to reduce code repetition
     * and ensure:
     * - Given list is not empty.
     * - Each item is an object (entity) and all same type.
     * - Each item has primary field definition.
     * - Each item has a unique state by primary with not null value.
     */
    private function prepareListItems(array|EntityList $entityList): array
    {
        $item  = $entityList[0] ?: throw new ManagerException('Empty list');
        $items = new ItemList();

        foreach ($entityList as $i => $entity) {
            is_object($entity) || throw new ManagerException(
                'Each item must be object, %t given', $entity
            );

            // All entities must be same type.
            if ($item::class != $entity::class) {
                throw new ManagerException(
                    'All items must be same type as first item %s, %s is different',
                    [$item::class, $entity::class]
                );
            }
            $item = $entity;

            /** @var froq\database\entity\ClassMeta */
            $classMeta = $this->getClassMeta($entity);

            if (!$primary = $classMeta->getTablePrimary()) {
                throw new ManagerException(
                    'Item %s[%d] has no primary (id) definition',
                    [$entity::class, $i]
                );
            }

            $id = $this->getPropertyValue($entity, $classMeta->getProperty($primary)->getReflection());
            if ($id === null) {
                throw new ManagerException(
                    'Item %s[%d] has null primary (%s) value',
                    [$entity::class, $i, $primary]
                );
            }
            if (!is_type_of($id, 'int|string')) {
                throw new ManagerException(
                    'Item %s[%d] primary (%s) value must be int|string, %t given',
                    [$entity::class, $i, $primary, $id]
                );
            }

            // Note: Since cannot get multiple records with same id and also corrupting
            // sorting processes in findAll() & removeAll(), this exception is required.
            if (isset($ids) && ($j = array_search($id, $ids)) !== false) {
                throw new ManagerException(
                    'Item %s[%d] has same primary (%s) value %s with previous item %s[%d]',
                    [$entity::class, $i, $primary, $id, $entity::class, $j]
                );
            }

            $ids[] = $id; $items[] = $entity;
        }

        return [$ids, $items, $primary, $classMeta];
    }

    /**
     * Sort items of both two lists by given primary for proper index selection (null-row safety)
     * in related loops in `findAll()` & `removeAll()` methods.
     */
    private function sortListItemsByPrimary(ItemList $recordList, ItemList $entityList, string $primary): void
    {
        $fn = fn($a, $b) => $a[$primary] <=> $b[$primary];

        $recordList->sort($fn); $entityList->sort($fn);
    }

    /**
     * Call an entity method if available, so defined in for find/save/remove actions.
     */
    private function callAction(object $entity, string $method): void
    {
        if (method_exists($entity, $method)) {
            $entity->$method();
        }
    }
}
