<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{ManagerException, MetaParser, AbstractEntity, AbstractEntityList,
    ClassMeta, PropertyMeta};
use froq\database\record\{Form, Record};
use froq\database\{Database, Query, Result, trait\DbTrait};
use froq\validation\Rule as ValidationRule;
use froq\pager\Pager;
use ReflectionClass, ReflectionProperty, Throwable;

/**
 * Manager.
 *
 * Represents a class entity that creates & manages data entities using attributes/annotations of these entities,
 * also dial with current open database for queries, executions and transactions.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\Manager
 * @author  Kerem Güneş
 * @since   5.0
 */
final class Manager
{
    /** @see froq\database\trait\DbTrait */
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
     * Run a raw SQL query.
     *
     * @param  string     $query
     * @param  array|null $params
     * @param  array|null $options
     * @return froq\database\Result
     */
    public function query(string $query, array $params = null, array $options = null): Result
    {
        return $this->db->query($query, $params, $options);
    }

    /**
     * Run a raw SQL execution.
     *
     * @param  string     $query
     * @param  array|null $params
     * @return int
     */
    public function execute(string $query, array $params = null): int
    {
        return $this->db->execute($query, $params);
    }

    /**
     * Run a SQL transaction or return a Transaction instance.
     *
     * @param  callable|null $call
     * @param  callable|null $callError
     * @return any
     */
    public function transaction(callable $call = null, callable $callError = null)
    {
        return $this->db->transaction($call, $callError);
    }

    /**
     * Create an entity with/without given properties.
     *
     * @param  string    $class
     * @param  any    ...$properties
     * @return object
     * @throws froq\database\entity\ManagerException
     * @causes froq\database\entity\ManagerException
     */
    public function createEntity(string $class, ...$properties): object
    {
        $entity = $this->initEntity($class, $properties);

        /* @var froq\database\entity\ClassMeta|null */
        $classMeta = MetaParser::parseClassMeta($entity);
        $classMeta || throw new ManagerException('Null entity class meta');

        $record = $this->initRecord($classMeta, $entity);

        $this->assignEntityProperties($entity, $record, $classMeta);
        $this->assignEntityInternalProperties($entity, $record);

        return $entity;
    }

    /**
     * Create an entity list.
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
     * @throws froq\database\entity\ManagerException
     */
    public function save(object $entity): object
    {
        /* @var froq\database\entity\ClassMeta|null */
        $classMeta = MetaParser::parseClassMeta($entity);
        $classMeta || throw new ManagerException('Null entity class meta');

        $entityData = $entityProps = [];
        foreach ($classMeta->getProperties() as $name => $propertyMeta) {
            $value = self::getPropertyValue($propertyMeta->getReflector(), $entity);

            // @cancel
            // // Collect & skip entity properties to save later.
            // if ($propertyMeta->hasEntity() && $propertyMeta->isLinkCascadesFor('save')) {
            //     // We can't save empty entities.
            //     if ($value != null) {
            //         $entityProps[] = $value;
            //     }
            //     continue;
            // }

            // Working without this, hmmm...
            // // Skip entity properties.
            // if ($propertyMeta->isLinked() || $propertyMeta->hasEntity()) {
            //     continue;
            // }

            $entityData[$name] = $value ?? $propertyMeta->getValidationDefault();
        }

        // No try/catch, so allow exceptions in Record.
        $record = $this->initRecord($classMeta, $entity)
                ->setData($entityData)
                ->save();

        $this->assignEntityProperties($entity, $record, $classMeta);
        $this->assignEntityInternalProperties($entity, $record);

        // @cancel
        // if ($record->isSaved()) {
        //     // Also save if any entity property exists.
        //     foreach ($entityProps as $entityProp) {
        //         $this->save($entityProp);
        //     }
        //     // Fill linked properties.
        //     foreach ($this->getLinkedProperties($classMeta) as $propertyMeta) {
        //         $this->loadLinkedProperty($propertyMeta, $entity, 'save');
        //     }
        // }

        // Call on-save method when provided.
        if (method_exists($entity, 'onSave')) {
            $entity->onSave();
        }

        return $entity;
    }

    /**
     * Save all given entities.
     *
     * @param  array|froq\database\entity\AbstractEntityList $entityList
     * @param  bool                                          $init
     * @return array|froq\database\entity\AbstractEntityList
     * @causes froq\database\entity\ManagerException
     */
    public function saveAll(array|AbstractEntityList $entityList, bool $init = false): array|AbstractEntityList
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
     * @param  object      $entity
     * @param  string|null $id
     * @return object
     * @throws froq\database\entity\ManagerException
     */
    public function find(object $entity, int|string $id = null): object
    {
        /* @var froq\database\entity\ClassMeta|null */
        $classMeta = MetaParser::parseClassMeta($entity);
        $classMeta || throw new ManagerException('Null entity class meta');

        $id   ??= self::getEntityPrimaryValue($entity, $classMeta);
        $fields = self::getEntityFields($entity);

        // No try/catch, so allow exceptions in Record.
        $record = $this->initRecord($classMeta)
                ->setId($id)
                ->return($fields)
                ->find();

        $this->assignEntityProperties($entity, $record, $classMeta);
        $this->assignEntityInternalProperties($entity, $record);

        // @cancel
        // if ($record->isFinded()) {
        //     // Fill linked properties.
        //     foreach ($this->getLinkedProperties($classMeta) as $propertyMeta) {
        //         $this->loadLinkedProperty($propertyMeta, $entity, 'find');
        //     }
        // }

        // Call on-find method when provided.
        if (method_exists($entity, 'onFind')) {
            $entity->onFind();
        }

        return $entity;
    }

    /**
     * Find all entity list related records & fill given entity list with found records data.
     *
     * @param  array|froq\database\entity\AbstractEntityList $entityList
     * @param  bool                                          $init
     * @return array|froq\database\entity\AbstractEntityList
     * @throws froq\database\entity\ManagerException
     * @causes froq\database\entity\ManagerException
     */
    public function findAll(array|AbstractEntityList $entityList, bool $init = false): array|AbstractEntityList
    {
        foreach ($entityList as $entity) {
            $this->find($entity);
        }

        if ($init && is_array($entityList)) {
            $entityList = $this->initEntityList(null, $entityList);
        }

        return $entityList;
    }

    /**
     * Find all entity records by given conditions & init/fill given entity class with found records data
     * when db supports, returning an entity list on success or null on failure.
     *
     * @param  string                 $entityClass
     * @param  array|null             $where
     * @param  int|null               $limit
     * @param  string|null            $order
     * @param  froq\pager\Pager|null &$pager
     * @return object|null
     * @throws froq\database\entity\ManagerException
     */
    public function findBy(string $entityClass, string|array $where = null, int $limit = null, string $order = null,
        Pager &$pager = null): object|null
    {
        /* @var froq\database\entity\ClassMeta|null */
        $classMeta = MetaParser::parseClassMeta($entityClass);
        $classMeta || throw new ManagerException('Null entity class meta');

        $entity = new $entityClass();
        $record = $this->initRecord($classMeta);
        $fields = self::getEntityFields($entity);

        $rows = $record->select($where ?? [], fields: $fields, limit: $limit, order: $order);
        if ($rows != null) {
            // For a proper list to loop below.
            if ($limit == 1) {
                $rows = [$rows];
            }

            foreach ($rows as $row) {
                $entityClone = clone $entity;
                $this->assignEntityProperties($entityClone, $row, $classMeta);
                $this->assignEntityInternalProperties($entityClone, $record);

                // @cancel
                // // Fill linked properties.
                // foreach ($this->getLinkedProperties($classMeta) as $propertyMeta) {
                //     $this->loadLinkedProperty($propertyMeta, $entityClone, 'find');
                // }

                // Call on-save method when provided.
                if (method_exists($entityClone, 'onFind')) {
                    $entityClone->onFind();
                }

                $entityClones[] = $entityClone;
            }

            // Create & fill entity list.
            $entityList = $this->initEntityList($classMeta->getListClass(), $entityClones);

            $pager && $entityList->setPager($pager);

            return $entityList;
        }

        return null;
    }

    /**
     * Remove an entity related record & fill given entity with found record data.
     *
     * @param  object      $entity
     * @param  string|null $id
     * @return object
     * @throws froq\database\entity\ManagerException
     */
    public function remove(object $entity, int|string $id = null): object
    {
        /* @var froq\database\entity\ClassMeta|null */
        $classMeta = MetaParser::parseClassMeta($entity);
        $classMeta || throw new ManagerException('Null entity class meta');

        $id   ??= self::getEntityPrimaryValue($entity, $classMeta);
        $fields = self::getEntityFields($entity);

        // No try/catch, so allow exceptions in Record.
        $record = $this->initRecord($classMeta)
                ->setId($id)
                ->return($fields)
                ->remove();

        $this->assignEntityProperties($entity, $record, $classMeta);
        $this->assignEntityInternalProperties($entity, $record);

        // @cancel
        // if ($record->isRemoved()) {
        //     // Drop linked properties (records actually).
        //     foreach ($this->getLinkedProperties($classMeta) as $propertyMeta) {
        //         $this->unloadLinkedProperty($propertyMeta, $entity);
        //     }
        // }

        // Call on-remove method when provided.
        if (method_exists($entity, 'onRemove')) {
            $entity->onRemove();
        }

        return $entity;
    }

    /**
     * Remove all entity list related records & fill given entity list with removed records data.
     *
     * @param  array|froq\database\entity\AbstractEntityList $entityList
     * @param  bool                                          $init
     * @return array|froq\database\entity\AbstractEntityList
     * @throws froq\database\entity\ManagerException
     * @causes froq\database\entity\ManagerException
     */
    public function removeAll(array|AbstractEntityList $entityList, bool $init = false): array|AbstractEntityList
    {
        foreach ($entityList as $entity) {
            $this->remove($entity);
        }

        if ($init && is_array($entityList)) {
            $entityList = $this->initEntityList(null, $entityList);
        }

        return $entityList;
    }

    /**
     * Remove all entity records by given conditions & init/fill given entity class with removed records data
     * when db supports, returning an entity list on success or null on failure.
     *
     * @param  string $entityClass
     * @param  array  $where
     * @return object|null
     * @throws froq\database\entity\ManagerException
     */
    public function removeBy(string $entityClass, string|array $where): object|null
    {
        /* @var froq\database\entity\ClassMeta|null */
        $classMeta = MetaParser::parseClassMeta($entityClass);
        $classMeta || throw new ManagerException('Null entity class meta');

        $entity = new $entityClass();
        $record = $this->initRecord($classMeta);
        $return = self::getEntityFields($entity);

        $rows = $record->delete($where, return: $return);
        if ($rows != null) {
            foreach ($rows as $row) {
                $entityClone = clone $entity;
                $this->assignEntityProperties($entityClone, $row, $classMeta);
                $this->assignEntityInternalProperties($entityClone, $record);

                // @cancel
                // // Drop linked properties (records actually).
                // foreach ($this->getLinkedProperties($classMeta) as $propertyMeta) {
                //     $this->unloadLinkedProperty($propertyMeta, $entityClone);
                // }

                // Call on-remove method when provided.
                if (method_exists($entityClone, 'onRemove')) {
                    $entityClone->onRemove();
                }

                $entityClones[] = $entityClone;
            }

            // Create & fill entity list.
            $entityList = $this->initEntityList($classMeta->getListClass(), $entityClones);

            return $entityList;
        }

        return null;
    }

    /**
     * Init a record by given entity class meta.
     *
     * @param  froq\database\entity\ClassMeta $classMeta
     * @param  object|null                    $entity
     * @return froq\database\record\Record
     */
    private function initRecord(ClassMeta $classMeta, object $entity = null): Record
    {
        $validations = null;

        // Assign validations if available.
        if ($entity != null) {
            $ref = $classMeta->getReflector();
            // When "validations" property is defined on entity class.
            if ($ref->hasProperty('validations')) {
                $validations = self::getPropertyValue(
                    new ReflectionProperty($ref->getName(), 'validations'),
                    $entity
                );
            }
            // When "validations()" method exists on entity class.
            elseif (is_callable_method($entity, 'validations')) {
                $validations = $entity::validations();
            }
            // When propties have "validation" metadata on entity class.
            else {
                foreach ($classMeta->getProperties() as $name => $propertyMeta) {
                    // Skip entity properties.
                    if ($propertyMeta->hasEntity()) {
                        continue;
                    }

                    $validations[$name] = $propertyMeta->getValidation();
                }
            }
        }

        // Use annotated record class or default.
        $record = $classMeta->getRecordClass() ?: Record::class;

        return new $record(
            $this->db,
            table: $classMeta->getTable(),
            tablePrimary: ($id = $classMeta->getTablePrimary()),
            options: [
                'transaction' => $classMeta->getOption('transaction', true),
                'sequence'    => $classMeta->getOption('sequence', !!$id),
                'validate'    => $classMeta->getOption('validate', !!$validations),
            ],
            validations: $validations,
        );
    }

    /**
     * Init an entity with/without given class & with/without given properties.
     *
     * @param  string|null $class
     * @param  array|null  $properties
     * @return froq\database\entity\AbstractEntity
     * @throws froq\database\entity\ManagerException
     */
    private function initEntity(string|null $class, array $properties = null): AbstractEntity
    {
        if ($class !== null) {
            // Check class validity.
            if (!class_extends($class, AbstractEntity::class)) {
                throw new ManagerException(
                    'Entity list class `%s` must extend `%s`',
                    [$class, AbstractEntity::class]
                );
            }

            $entity = new $class();
        } else {
            $entity = new class() extends AbstractEntity {};
        }

        // Set manager & properties.
        $entity->setManager($this);
        $properties && $entity->fill(...$properties);

        return $entity;
    }

    /**
     * Init an entity list with/without given class & with/without given entities.
     *
     * @param  string|null $class
     * @param  array|null  $entities
     * @return froq\database\entity\AbstractEntity
     * @throws froq\database\entity\ManagerException
     */
    private function initEntityList(string|null $class, array $entities = null): AbstractEntityList
    {
        if ($class !== null) {
            // Check class validity.
            if (!class_extends($class, AbstractEntityList::class)) {
                throw new ManagerException(
                    'Entity list class `%s` must extend `%s`',
                    [$class, AbstractEntityList::class]
                );
            }

            $entityList = new $class();
        } else {
            $entityList = new class() extends AbstractEntityList {};
        }

        // Set manager & stack items.
        $entityList->setManager($this);
        $entities && $entityList->resetData($entities);

        return $entityList;
    }

    /**
     * Get linked properties from given entity class meta.
     *
     * @param  froq\database\entity\ClassMeta $classMeta
     * @return array
     */
    private function getLinkedProperties(ClassMeta $classMeta): array
    {
        return array_filter($classMeta->getProperties(), fn($propertyMeta) => $propertyMeta->isLinked());
    }

    /**
     * Load a linked property.
     *
     * @param  froq\database\entity\PropertyMeta $propertyMeta
     * @param  object                            $entity
     * @param  string|null                       $action
     * @return void
     * @throws froq\database\entity\ManagerException
     * @cancel Not in use.
     */
    private function loadLinkedProperty(PropertyMeta $propertyMeta, object $entity, string $action = null): void
    {
        // Check whether cascade op allows given action.
        if ($action && !$propertyMeta->isLinkCascadesFor($action)) {
            return;
        }

        $class = $propertyMeta->getEntityClass() ?: throw new ManagerException(
            'No valid link entity provided in `%s` meta',
            $propertyMeta->getName()
        );

        [$table, $column, $condition, $method, $limit] = $propertyMeta->packLinkStuff();

        // Check non-link / non-valid properties.
        ($table && $column) ?: throw new ManagerException(
            'No valid link table/column provided in `%s` meta',
            $propertyMeta->getName()
        );

        // Given or default limit (if not disabled as "-1").
        $limit = ($limit != -1) ? $limit : null;

        // Parse linked property class meta.
        /* @var froq\database\entity\ClassMeta|null */
        $linkedClassMeta = MetaParser::parseClassMeta($class);
        $linkedClassMeta || throw new ManagerException('Null entity class meta');

        switch ($method) {
            case 'one-to-one':
                $primaryField = $linkedClassMeta->getTablePrimary();
                $primaryValue = self::getPropertyValue($column, $entity);

                $limit = 1; // Update limit.
                break;
            case 'one-to-many':
                $primaryField = $column; // Reference.

                // Get value from property's class.
                /* @var froq\database\entity\ClassMeta|null */
                $propertyClassMeta  = MetaParser::parseClassMeta($propertyMeta->getClass());
                $propertyClassMeta || throw new ManagerException('Null entity class meta');

                $primaryValue = self::getPropertyValue($propertyClassMeta->getTablePrimary(), $entity);

                unset($propertyClassMeta); // Free.
                break;
            default:
                throw new ManagerException(
                    'Unimplemented link method `%s` on `%s` property',
                    [$method, $propertyMeta->getName()]
                );
        }

        $fields = self::getEntityFields($class);

        // Create a select query & apply link criteria.
        $query = $this->db->initQuery($table)
               ->select($fields)
               ->equal($primaryField, $primaryValue);

        // Apply link (row) limit.
        $limit && $query->limit($limit);

        // Apply where condition.
        if ($condition != null) {
            $query->where('(' . str_replace(
                ['==', '&', '|'], ['=', 'AND', 'OR'],
                $condition
            ) . ')');
        }

        $propEntity     = new $class();
        $propEntityList = ($listClass = $propertyMeta->getEntityListClass()) ? new $listClass() : null;

        // An entity list.
        if ($propEntityList != null) {
            $data = (array) $query->getArrayAll($pager, $limit);
            foreach ($data as $dat) {
                $propEntityClone = clone $propEntity;
                foreach ($dat as $name => $value) {
                    $prop = $linkedClassMeta->getProperty($name);
                    $prop ? self::setPropertyValue($prop->getReflector(), $propEntityClone, $value)
                          : throw new ManagerException('Property `%s.%s` not exists or private', [$class, $name]);
                }

                $propEntityList->add($propEntityClone);
            }

            $pager && $propEntityList->setPager($pager);

            // Set property value as an entity list.
            self::setPropertyValue($propertyMeta->getReflector(), $entity, $propEntityList);
        }
        // An entity.
        else {
            $data = (array) $query->getArray();
            foreach ($data as $name => $value) {
                $prop = $linkedClassMeta->getProperty($name);
                $prop ? self::setPropertyValue($prop->getReflector(), $propEntity, $value)
                      : throw new ManagerException('Property `%s.%s` not exists or private', [$class, $name]);
            }

            // Recursion for other linked stuff.
            foreach ($this->getLinkedProperties($linkedClassMeta) as $prop) {
                $this->loadLinkedProperty($prop, $propEntity, $action);
            }

            // Set property value as an entity.
            self::setPropertyValue($propertyMeta->getReflector(), $entity, $propEntity);
        }
    }

    /**
     * Unload a linked property (drops a record from database actually).
     *
     * Note: seems it's nonsence loading whole dropped linked data on entities, this method does
     * not create and fill dropped records data as new entities. So, a property (eg. User$logins)
     * can contain a plenty records on a database.
     *
     * @param  froq\database\entity\PropertyMeta $propertyMeta
     * @param  object                            $entity
     * @return void
     * @throws froq\database\entity\ManagerException
     * @cancel Not in use.
     */
    private function unloadLinkedProperty(PropertyMeta $propertyMeta, object $entity): void
    {
        // Check whether cascade op allows remove action.
        if (!$propertyMeta->isLinkCascadesFor('remove')) {
            return;
        }

        $class = $propertyMeta->getEntityClass() ?: throw new ManagerException(
            'No valid link entity provided in `%s` meta',
            $propertyMeta->getName()
        );

        [$table, $column, , $method] = $propertyMeta->packLinkStuff();

        // Check non-link / non-valid properties.
        ($table && $column) ?: throw new ManagerException(
            'No valid link table/column provided in `%s` meta',
            $propertyMeta->getName()
        );

        // Parse linked property class meta.
        /* @var froq\database\entity\ClassMeta|null */
        $linkedClassMeta = MetaParser::parseClassMeta($class);
        $linkedClassMeta || throw new ManagerException('Null entity class meta');

        switch ($method) {
            case 'one-to-one':
                $primaryField = $linkedClassMeta->getTablePrimary();
                $primaryValue = self::getPropertyValue($column, $entity);
                break;
            case 'one-to-many':
                $primaryField = $column; // Reference.

                // Get value from property's class.
                /* @var froq\database\entity\ClassMeta|null */
                $propertyClassMeta  = MetaParser::parseClassMeta($propertyMeta->getClass());
                $propertyClassMeta || throw new ManagerException('Null entity class meta');

                $primaryValue = self::getPropertyValue($propertyClassMeta->getTablePrimary(), $entity);

                unset($propertyClassMeta); // Free.
                break;
            default:
                throw new ManagerException(
                    'Unimplemented link method `%s` on `%s` property',
                    [$method, $propertyMeta->getName()]
                );
        }

        // Create a delete query & apply link criteria.
        $this->db->initQuery($table)
                 ->delete()
                 ->equal($primaryField, $primaryValue)
                 ->run();
    }

    /**
     * Assign an entity's properties.
     *
     * @param  object                            $entity
     * @param  array|froq\database\record\Record $record
     * @param  froq\database\entity\ClassMeta    $classMeta
     * @return void
     */
    private function assignEntityProperties(object $entity, array|Record $record, ClassMeta $classMeta): void
    {
        $data = is_array($record) ? $record : $record->getData();

        if ($data) {
            $props = $classMeta->getProperties();
            foreach ($data as $name => $value) {
                // Set existsing (defined/parsed) properties only.
                isset($props[$name]) && self::setPropertyValue(
                    $props[$name]->getReflector(), $entity, $value
                );
            }
        }
    }

    /**
     * Assign an entity's internal properties.
     *
     * @param  object                      $entity
     * @param  froq\database\record\Record $record
     * @return void
     */
    private function assignEntityInternalProperties(object $entity, Record $record): void
    {
        // When entity extends AbstractEntity.
        if ($entity instanceof AbstractEntity) {
            $entity->setManager($this)
                   ->setRecord($record);
        }
    }

    /**
     * Set an entity's property value.
     *
     * @param  string|ReflectionProperty $ref
     * @param  object                    $entity
     * @param  any                       $value
     * @return void
     */
    private static function setPropertyValue(string|ReflectionProperty $ref, object $entity, $value): void
    {
        is_string($ref) && $ref = new ReflectionProperty($entity, $ref);

        // When a property-specific setter is available.
        if (is_callable_method($entity, $method = ('set' . $ref->name))) {
            $entity->$method($value);
            return;
        }

        $ref->isPublic() || $ref->setAccessible(true);

        $ref->setValue($entity, $value);
    }

    /**
     * Get an entity's property value.
     *
     * @param  string|ReflectionProperty $ref
     * @param  object                    $entity
     * @return any
     */
    private static function getPropertyValue(string|ReflectionProperty $ref, object $entity)
    {
        is_string($ref) && $ref = new ReflectionProperty($entity, $ref);

        // When a property-specific getter is available.
        if (is_callable_method($entity, $method = ('get' . $ref->name))) {
            return $entity->$method();
        }

        $ref->isPublic() || $ref->setAccessible(true);

        return $ref->getValue($entity);
    }

    /**
     * Get an entity's fields when defined `fields()` method as static or return `*`.
     *
     * @param  object|string $entity
     * @return array|string
     * @throws froq\database\entity\ManagerException
     */
    private static function getEntityFields(object|string $entity): array|string
    {
        // Default is all.
        $fields = '*';

        // When fields() method available as public & static.
        if (is_callable_method($entity, 'fields')) {
            $fields = $entity::fields();
            is_array($fields) || is_string($fields) || throw new ManagerException(
                'Method %s.fields() must return array|string, %s returned',
                [is_object($entity) ? $entity::class : $entity, get_type($fields)]
            );

            // Prevent weird issues.
            if (!$fields || $fields === ['*']) {
                $fields = '*';
            }
        }

        return $fields;
    }

    /**
     * Get an entity's primary value from given entity class meta when available.
     *
     * @param  object                         $entity
     * @param  froq\database\entity\ClassMeta $classMeta
     * @return int|string|null
     */
    private static function getEntityPrimaryValue(object $entity, ClassMeta $classMeta): int|string|null
    {
        $primary = (string) $classMeta->getTablePrimary();

        // When defined as public.
        if (isset($entity->{$primary})) {
            return $entity->{$primary};
        }
        // When getId() defined as public.
        elseif (is_callable_method($entity, 'getId')) {
            return $entity->getId();
        }

        return null;
    }
}
