<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\meta\{MetaParser, Meta, ClassMeta, PropertyMeta};
use froq\database\{Database, DatabaseRegistry, DatabaseRegistryException, Query};
use froq\database\{common\Table, record\Record, trait\DbTrait};
use froq\validation\ValidationError;
use froq\pager\Pager;
use ItemList, ReflectionProperty, ReflectionMethod, XReflectionType;
use DateTime, DateTimeImmutable, DateTimeInterface;

/**
 * Entity manager class for creating & managing entities.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\EntityManager
 * @author  Kerem Güneş
 * @since   5.0
 */
class EntityManager
{
    use DbTrait;

    /**
     * Constructor.
     *
     * @param  froq\database\Database|null $db
     * @throws froq\database\entity\EntityManagerException
     */
    public function __construct(Database $db = null)
    {
        if (!$db) try {
            $db = DatabaseRegistry::getDefault();
        } catch (DatabaseRegistryException $e) {
            throw new EntityManagerException($e);
        }

        $this->db = $db;
    }

    /**
     * Create an entity with/without given properties.
     *
     * @param  string    $class
     * @param  mixed  ...$properties
     * @return object
     * @causes froq\database\entity\EntityManagerException
     */
    public function createEntity(string $class, mixed ...$properties): object
    {
        /** @var froq\database\entity\meta\ClassMeta */
        $classMeta = $this->getClassMeta($class);

        $entity = $this->initEntity($class, $properties);
        $record = $this->initRecord($classMeta, $entity, true, true);

        $this->setPropertyValues($entity, $record, $classMeta);
        $this->setInternalPropertyValues($entity, $record);

        return $entity;
    }

    /**
     * Create an entity list with/without given entities.
     *
     * @param  string    $class
     * @param  object ...$entities
     * @return object
     * @causes froq\database\entity\EntityManagerException
     */
    public function createEntityList(string $class, object ...$entities): object
    {
        return $this->initEntityList($class, $entities);
    }

    /**
     * Get an entity meta.
     *
     * @param  string|object $entity
     * @param  string|null   $property
     * @return froq\database\entity\meta\Meta|null
     * @causes froq\database\entity\meta\MetaException
     */
    public function getMeta(string|object $entity, string $property = null): Meta|null
    {
        try {
            return (func_num_args() == 1)
                 ? $this->getClassMeta($entity)
                 : $this->getPropertyMeta($entity, $property);
        }
        // Catch manager exception only, not "no class exists" etc.
        catch (EntityManagerException) {
            return null;
        }
    }

    /**
     * Save an entity.
     *
     * @param  object $entity
     * @return object
     * @causes froq\database\entity\EntityManagerException
     * @throws froq\validation\ValidationError
     */
    public function save(object $entity): object
    {
        /** @var froq\database\entity\meta\ClassMeta */
        $classMeta = $this->getClassMeta($entity);

        $data = [];
        foreach ($classMeta->getPropertyMetas() as $propertyMeta) {
            if ($field = $propertyMeta->getField()) {
                $data[$field] = $this->getPropertyValue($entity, $propertyMeta->getReflection())
                    ?? $propertyMeta->getValidationDefault();
            }
        }

        // Clear null values & discard validations for "update" actions only on existing records.
        // So, if any non nullable field was sent to db, an error will be raised already.
        $id = $this->getPrimaryValue($entity, $classMeta, check: false);
        if ($id !== null) {
            $data = array_refine($data, [null]);
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
                'Cannot save entity (%s), validation failed [tip: %s]',
                [$entity::class, ValidationError::tip()], errors: $e->errors()
            );
        }

        $this->setPropertyValues($entity, $record, $classMeta);
        $this->setInternalPropertyValues($entity, $record, ['saved', $record->isSaved()]);

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
     * @causes froq\database\entity\EntityManagerException
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
     * @causes froq\database\entity\EntityManagerException
     */
    public function find(object $entity): object
    {
        /** @var froq\database\entity\meta\ClassMeta */
        $classMeta = $this->getClassMeta($entity);

        $id = $this->getPrimaryValue($entity, $classMeta);

        /** @var froq\database\record\Record */
        $record = $this->initRecord($classMeta, $entity, true, primaryRequired: true);

        // Call action method when provided.
        $this->callAction($entity, 'onQuery', $record->getQuery(), /* $id */);

        /** @var froq\database\record\Record */
        $record = $record->find($id);

        $this->setPropertyValues($entity, $record, $classMeta);
        $this->setInternalPropertyValues($entity, $record, ['finded', $record->isFinded()]);

        // Call action method when provided.
        $this->callAction($entity, 'onFind', $record->getData());

        return $entity;
    }

    /**
     * Find all entity list related records & fill given entity list with found records data.
     *
     * @param  array<object>|froq\database\entity\EntityList<object> $entityList
     * @param  bool                                                  $init
     * @return array<object>|froq\database\entity\EntityList<object>
     * @causes froq\database\entity\EntityManagerException
     */
    public function findAll(array|EntityList $entityList, bool $init = false): array|EntityList
    {
        [$ids, $items, $primary, $classMeta] = $this->prepareListItems($entityList);

        /** @var froq\database\record\RecordList */
        $record = $this->initRecord($classMeta, $entity = $items[0], true);

        // Call action method when provided.
        $this->callAction($entity, 'onQuery', $record->getQuery(), /* $ids */);

        /** @var froq\database\record\RecordList */
        $records = $record->findAll($ids);

        if ($records->count()) {
            // For proper index selection below (null-row safety).
            $this->sortListItemsByPrimary($records, $items, $primary);

            foreach ($records as $i => $record) {
                $entity = $items[$i];

                $this->setPropertyValues($entity, $record, $classMeta);
                $this->setInternalPropertyValues($entity, $record, ['finded', true]);

                // Call action method when provided.
                $this->callAction($entity, 'onFind', $record->getData());
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
     * $limit = Get from somewhere or set as constant.     // default = 10
     * $count = Count of total records by some conditions. // optional
     *
     * $pager = new Pager(['page' => $page, 'limit' => $limit, 'count' => $count]);
     * $pager->run(); // optional
     *
     * $result = $em->findBy(entity object or class, pager: $pager, ...other params);
     *```
     *
     * @param  object|string                         $entity
     * @param  string|array|froq\database\Query|null $where  Discards entity props when it is Query.
     * @param  array|null                            $params Used only when $where is string.
     * @param  string|null                           $order
     * @param  int|null                              $limit
     * @param  int|null                              $offset
     * @param  froq\pager\Pager|null                &$pager
     * @return froq\database\entity\EntityList
     * @causes froq\database\entity\EntityManagerException
     */
    public function findBy(object|string $entity, string|array|Query $where = null, array $params = null,
        string $order = null, int $limit = null, int $offset = null, Pager &$pager = null): EntityList
    {
        // When no entity instance given.
        is_string($entity) && $entity = new $entity();

        /** @var froq\database\entity\meta\ClassMeta */
        $classMeta = $this->getClassMeta($entity);

        // Use Query's where dropping "WHERE" part.
        if ($where instanceof Query) {
            $where = substr($where->toQueryString('where'), 6);
        } else {
            $this->prepareWhere($entity, $classMeta, $where, $params);
        }

        /** @var froq\database\record\Record */
        $record = $this->initRecord($classMeta, $entity, true);

        // Call action method when provided.
        $this->callAction($entity, 'onQuery', $record->getQuery(), /* null or getPrimaryValue() if entity is object */);

        if ($pager) {
            // Disable redirects.
            $pager->redirect = false;

            // Pager was run?
            if ($pager->totalPages === null) {
                $count = $pager->totalRecords ?? $record->count($where, $params);
                $pager->run($count);
            }

            [$limit, $offset] = [$pager->limit, $pager->offset];
        }

        // Order by clause, default is primary.
        $order ??= $classMeta->getTablePrimary();

        /** @var froq\database\record\RecordList */
        $records = $record->findBy($where, $params,
            limit: $limit, offset: $offset, order: $order, fetch: 'array');

        $entityList = $this->initEntityList($classMeta->getListClass());

        if ($records->count()) {
            foreach ($records as $record) {
                $entityClone = clone $entity;

                $this->setPropertyValues($entityClone, $record, $classMeta);
                $this->setInternalPropertyValues($entityClone, $record, ['finded', true]);

                // Call action method when provided.
                $this->callAction($entityClone, 'onFind', $record->getData());

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
     * @causes froq\database\entity\EntityManagerException
     */
    public function remove(object $entity): object
    {
        /** @var froq\database\entity\meta\ClassMeta */
        $classMeta = $this->getClassMeta($entity);

        $id = $this->getPrimaryValue($entity, $classMeta);

        /** @var froq\database\record\Record */
        $record = $this->initRecord($classMeta, $entity, true, primaryRequired: true)
            ->remove($id);

        $this->setPropertyValues($entity, $record, $classMeta);
        $this->setInternalPropertyValues($entity, $record, ['removed', $record->isRemoved()]);

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
     * @causes froq\database\entity\EntityManagerException
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

                $this->setPropertyValues($entity, $record, $classMeta);
                $this->setInternalPropertyValues($entity, $record, ['removed', true]);

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
     * @param  string|object                         $entity
     * @param  string|array|froq\database\Query|null $where  Discards entity props when it is Query.
     * @param  array|null                            $params Used only when $where is string.
     * @return froq\database\entity\EntityList
     * @causes froq\database\entity\EntityManagerException
     */
    public function removeBy(string|object $entity, string|array|Query $where = null, array $params = null): EntityList
    {
        // When no entity instance given.
        is_string($entity) && $entity = new $entity();

        /** @var froq\database\entity\meta\ClassMeta */
        $classMeta = $this->getClassMeta($entity);

        // Use Query's where dropping "WHERE" part.
        if ($where instanceof Query) {
            $where = substr($where->toQueryString('where'), 6);
        } else {
            $this->prepareWhere($entity, $classMeta, $where, $params);
        }

        /** @var froq\database\record\RecordList */
        $records = $this->initRecord($classMeta, $entity, true)
            ->removeBy($where, $params);

        $entityList = $this->initEntityList($classMeta->getListClass());

        if ($records->count()) {
            foreach ($records as $record) {
                $entityClone = clone $entity;

                $this->setPropertyValues($entityClone, $record, $classMeta);
                $this->setInternalPropertyValues($entityClone, $record, ['removed', true]);

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
     *
     * @throws froq\database\entity\EntityManagerException
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
            throw new EntityManagerException('Entity %s has no table definition',
                $classMeta->getName());
        }
        if ($primaryRequired && !$tablePrimary) {
            throw new EntityManagerException('Entity %s has no primary (id) definition',
                $classMeta->getName());
        }

        $record = new $record(
            db: $this->db,
            table: new Table($table, $tablePrimary),
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
     *
     * @throws froq\database\entity\EntityManagerException
     */
    private function initEntity(string|null $class, array $properties = null): Entity
    {
        // Check class validity.
        if ($class) {
            class_exists($class) || throw new EntityManagerException(
                'Entity class `%s` not exists', $class
            );
            class_extends($class, Entity::class) || throw new EntityManagerException(
                'Entity class `%s` must extend `%s`', [$class, Entity::class]
            );

            $entity = new $class();
        } else {
            $entity = new class() extends Entity {};
        }

        // Set manager & properties.
        $entity->proxy()->setManager($this);
        $properties && $entity->fill(...$properties);

        return $entity;
    }

    /**
     * Init an entity list with/without given class & with/without given entities.
     *
     * @throws froq\database\entity\EntityManagerException
     */
    private function initEntityList(string|null $class, array $entities = null): EntityList
    {
        // Check class validity.
        if ($class) {
            class_exists($class) || throw new EntityManagerException(
                'Entity list class `%s` not exists', $class
            );
            class_extends($class, EntityList::class) || throw new EntityManagerException(
                'Entity list class `%s` must extend `%s`', [$class, EntityList::class]
            );

            $entityList = new $class();
        } else {
            $entityList = new class() extends EntityList {};
        }

        // Set manager & stack items.
        $entityList->proxy()->setManager($this);
        $entities && $entityList->fill(...$entities);

        return $entityList;
    }

    /**
     * Get a class meta parsing given entity meta attributes/annotations or throw an
     * `EntityManagerException` if given entity has no meta attributes/annotations.
     *
     * @throws froq\database\entity\EntityManagerException
     * @causes froq\database\entity\meta\MetaException
     */
    private function getClassMeta(string|object $entity): ClassMeta
    {
        return MetaParser::parseClassMeta($entity)
            ?: throw new EntityManagerException('No meta on class %s', get_class_name($entity));
    }

    /**
     * Get a property meta parsing given entity property meta attributes/annotations or throw an
     * `EntityManagerException` if given entity property has no meta attributes/annotations.
     *
     * @throws froq\database\entity\EntityManagerException
     * @causes froq\database\entity\meta\MetaException
     */
    private function getPropertyMeta(string|object $entity, string $property): PropertyMeta
    {
        return MetaParser::parsePropertyMeta($entity, $property)
            ?: throw new EntityManagerException('No meta on class property %s:$s', get_class_name($entity), $property);
    }

    /**
     * Get an entity fields when defined `FIELDS` constant or `fields()` method
     * on entity class, or get them from class meta or return `*` (all) as default.
     *
     * @throws froq\database\entity\EntityManagerException
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
                throw new EntityManagerException($message, [$entity::class, $ret]);
            }
        }

        if (!$def) {
            foreach ($classMeta->getPropertyMetas() as $propertyMeta) {
                // Skip entity properties & non-fields (with no "field" definition).
                if ($propertyMeta->hasEntityClass() || !($field = $propertyMeta->getField())) {
                    continue;
                }

                $ret[] = $field;
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
     *
     * @throws froq\database\entity\EntityManagerException
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
                throw new EntityManagerException($message, [$entity::class, $ret]);
            }
        }

        // When properties have "validation" meta on entity class.
        if (!$def) {
            foreach ($classMeta->getPropertyMetas() as $propertyMeta) {
                // Skip entity properties & non-fields (with no "field" definition).
                if ($propertyMeta->hasEntityClass() || !($field = $propertyMeta->getField())) {
                    continue;
                }

                $ret[$field] = $propertyMeta->getValidation();
            }
        }

        return $ret;
    }

    /**
     * Set entity property values received from record data.
     */
    private function setPropertyValues(object $entity, array|Record $record, ClassMeta $classMeta): void
    {
        $data = is_array($record) ? $record : $record->toArray();

        foreach ($data as $name => $value) {
            // Set present (defined/parsed) properties only.
            if ($propertyMeta = $classMeta->getPropertyMeta($name)) {
                $this->setPropertyValue($entity, $propertyMeta->getReflection(), $value);
            }
        }
    }

    /**
     * Set entity internal property values used in the manager.
     */
    private function setInternalPropertyValues(object $entity, Record $record, array $state = null): void
    {
        // When entity extends Entity.
        if ($entity instanceof Entity) {
            $entity->proxy()->setManager($this);

            // Set result state.
            $state && $entity->proxy()->setState(...$state);
        }
    }

    /**
     * Get an entity primary value using given entity class meta when available.
     *
     * @throws froq\database\entity\EntityManagerException
     */
    private function getPrimaryValue(object $entity, ClassMeta $classMeta, bool $check = true): int|string|null
    {
        $primary = (string) $classMeta->getTablePrimary();

        if ($primary) {
            $primaryMeta = $classMeta->getPropertyMeta($primary);
            if (!$primaryMeta) {
                throw new EntityManagerException(
                    'Primary (%s::$%s) not defined or has no meta',
                    [$entity::class, $primary]
                );
            }

            $value = $this->getPropertyValue($entity, $primaryMeta->getReflection());
            if ($check && !is_type_of($value, 'int|string')) {
                throw new EntityManagerException(
                    'Primary (%s::$%s) value must be int|string, %t given',
                    [$entity::class, $primary, $value]
                );
            }
        }

        return $value ?? null;
    }

    /**
     * Set an entity property value.
     *
     * @throws froq\database\entity\EntityManagerException
     * @todo Use ObjectMapper?
     */
    private function setPropertyValue(object $entity, ReflectionProperty $property, mixed $value): void
    {
        // When property-specific setter is available.
        if (method_exists($entity, ($method = ('set' . $property->name)))) {
            $entity->$method($value);
            return;
        }

        // Typed properties.
        if ($property->hasType()) {
            $valueType    = get_type($value);
            $propertyType = XReflectionType::from($property->getType());
            $canSet       = $propertyType->contains($valueType);

            // Try to cast.
            if ($propertyType->getPureName() !== $valueType) {
                // Internals (eg: int, float).
                if ($propertyType->isCastable()) {
                    settype($value, $propertyType->getName());
                    $canSet = true;
                }
                // Classes (eg: DateTime).
                elseif ($value !== null) {
                    $class = null;

                    // Special case of date/time stuff (interface, subclass or union).
                    if (($interface = $propertyType->isClassOf(DateTimeInterface::class))
                        || $propertyType->contains(DateTime::class, DateTimeImmutable::class, DateTimeInterface::class)
                    ) {
                        $class = $interface ? DateTime::class : $propertyType->names()->find(
                            fn(string $name): bool => is_subclass_of($name, DateTimeInterface::class)
                        );
                    }
                    // Single classes.
                    elseif ($propertyType->isClass()) {
                        $class = $propertyType->getPureName();
                    }
                    // Choose a class (for unions).
                    else foreach ($propertyType->getNames() as $name) {
                        if (class_exists($name)) {
                            $class = $name;
                            break;
                        }
                    }

                    if ($class !== null) {
                        class_exists($class) || throw new EntityManagerException(
                            'Class %q not found for typed property %s::$%s',
                            [$class, $property->class, $property->name]
                        );

                        $value  = new $class($value);
                        $canSet = true;
                    }
                }
            }

            // Prevent invalid types.
            if (!$canSet) {
                return;
            }
        }

        $property->setValue($entity, $value);
    }

    /**
     * Get an entity property value.
     *
     * @todo Use ObjectMapper?
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
            foreach ($classMeta->getPropertyMetas() as $propertyMeta) {
                // Skip entity properties & non-fields (with no "field" definition).
                if ($propertyMeta->hasEntityClass() || !($field = $propertyMeta->getField())) {
                    continue;
                }

                // When "where" does not contains a condition already.
                if (!array_key_exists($field, $where)) {
                    $value = $this->getPropertyValue($entity, $propertyMeta->getReflection());
                    // Skip nulls.
                    if ($value !== null) {
                        $where[$field] = $value;
                    }
                }
            }
        }
    }

    /**
     * Prepare list items for `findAll()` & `removeAll()` methods to reduce code repetition and ensure:
     * - Given list is not empty.
     * - Each item is an object (entity) and all same type.
     * - Each item has primary field definition.
     * - Each item has a unique state by primary with not null value.
     *
     * @throws froq\database\entity\EntityManagerException
     */
    private function prepareListItems(array|EntityList $entityList): array
    {
        $item  = $entityList[0] ?: throw new EntityManagerException('Empty list');
        $items = new ItemList();

        foreach ($entityList as $i => $entity) {
            is_object($entity) || throw new EntityManagerException(
                'Each item must be object, %t given', $entity
            );

            // All entities must be same type.
            if ($item::class != $entity::class) {
                throw new EntityManagerException(
                    'All items must be same type as first item %s, %s is different',
                    [$item::class, $entity::class]
                );
            }
            $item = $entity;

            /** @var froq\database\entity\meta\ClassMeta */
            $classMeta = $this->getClassMeta($entity);

            if (!$primary = $classMeta->getTablePrimary()) {
                throw new EntityManagerException(
                    'Item %s[%d] has no primary (id) definition',
                    [$entity::class, $i]
                );
            }

            $primaryMeta = $classMeta->getPropertyMeta($primary);
            if (!$primaryMeta) {
                throw new EntityManagerException(
                    'Item %s[%d] primary (%s) not defined or has no meta',
                    [$entity::class, $i, $primary]
                );
            }

            $id = $this->getPropertyValue($entity, $primaryMeta->getReflection());
            if ($id === null) {
                throw new EntityManagerException(
                    'Item %s[%d] has null primary (%s) value',
                    [$entity::class, $i, $primary]
                );
            }
            if (!is_type_of($id, 'int|string')) {
                throw new EntityManagerException(
                    'Item %s[%d] primary (%s) value must be int|string, %t given',
                    [$entity::class, $i, $primary, $id]
                );
            }

            // Note: Since cannot get multiple records with same id and also corrupting
            // sorting processes in findAll() & removeAll(), this exception is required.
            if (isset($ids) && ($j = array_search($id, $ids)) !== false) {
                throw new EntityManagerException(
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
        $fn = fn($a, $b) => $a->$primary <=> $b->$primary;

        $recordList->sort($fn); $entityList->sort($fn);
    }

    /**
     * Call an entity method if defined in Entity for find/save/remove/query actions.
     *
     * Note: These methods always called regardless if they are public or encapsulaed
     * as private/protected. So they can be hidden from outer scope to prevent outer
     * calls by users.
     */
    private function callAction(object $entity, string $method, mixed ...$methodArgs): void
    {
        if ($entity instanceof Entity && method_exists($entity, $method)) {
            $ref = new ReflectionMethod($entity, $method);
            $ref->invokeArgs($entity, $methodArgs);
        }
    }
}
