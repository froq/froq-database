<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{ManagerException, MetaParser, AbstractEntity, AbstractEntityList};
use froq\database\record\{Form, Record};
use froq\database\{Database, Query, Result, trait\DbTrait};
use froq\validation\Rule as ValidationRule;
use froq\pager\Pager;
use ReflectionClass, ReflectionProperty, Throwable;

final class Manager
{
    /** @see froq\database\trait\DbTrait */
    use DbTrait;

    public function __construct(Database $db = null)
    {
        // Try to use active app database object.
        $db ??= function_exists('app') ? app()->database() : throw new ManagerException(
            'No database given to deal, be sure running this module with `froq\app` ' .
            'module and be sure `database` option exists in app config or pass $db argument'
        );

        $this->db = $db;
    }

    public function query(string $query, array $params = null, array $options = null): Result
    {
        return $this->db->query($query, $params, $options);
    }

    public function execute(string $query, array $params = null): int|null
    {
        return $this->db->execute($query, $params);
    }

    public function transaction(callable $call = null, callable $callError = null)
    {
        return $this->db->transaction($call, $callError);
    }

    public function save(object $entity): object
    {
        $ecMeta = MetaParser::parse($entity::class);

        $entityData = $entityProps = [];
        foreach ($ecMeta->getProperties() as $name => $epMeta) {
            $value = self::getPropertyValue($epMeta->getReflector(), $entity);

            // Collect & skip entity properties to save later.
            if ($epMeta->hasEntity()) {
                // We can't save empty entities.
                if ($value != null) {
                    $entityProps[] = $value;
                }
                continue;
            }

            $entityData[$name] = $value ?? $epMeta->getValidationDefault();
        }

        // No try/catch, so allow exceptions in Record.
        $record = $this->initRecord($ecMeta, $entity)
                ->setData($entityData)
                ->save();

        self::assignEntityRecord($entity, $record);
        self::assignEntityProperties($entity, $record, $ecMeta);

        if ($record->isSaved()) {
            // Also save if any entity property exists.
            foreach ($entityProps as $entityProp) {
                $this->save($entityProp);
            }

            // Fill linked properties.
            foreach ($this->getLinkedProperties($ecMeta) as $epMeta) {
                $this->loadLinkedProperty($epMeta, $entity, 'save');
            }
        }

        return $entity;
    }

    public function find(object $entity, int|string $id = null): object
    {
        $ecMeta = MetaParser::parse($entity::class);

        $id   ??= self::getEntityPrimaryValue($entity, $ecMeta);
        $fields = self::getEntityFields($entity);

        // No try/catch, so allow exceptions in Record.
        $record = $this->initRecord($ecMeta)
                ->setId($id)
                ->return($fields)
                ->find();

        self::assignEntityRecord($entity, $record);
        self::assignEntityProperties($entity, $record, $ecMeta);

        if ($record->isFinded()) {
            // Fill linked properties.
            foreach ($this->getLinkedProperties($ecMeta) as $epMeta) {
                $this->loadLinkedProperty($epMeta, $entity, 'find');
            }
        }

        return $entity;
    }

    public function findBy(string $entityClass, string|array $where = null, int $limit = null, string $order = null,
        Pager &$pager = null): object|null
    {
        $ecMeta = MetaParser::parse($entityClass);

        $entity = new $entityClass();
        $record = $this->initRecord($ecMeta);
        $fields = self::getEntityFields($entity);

        $rows = $record->select($where ?? [], fields: $fields, limit: $limit, order: $order);
        if ($rows != null) {
            // For a proper list to loop below.
            if ($limit == 1) {
                $rows = [$rows];
            }

            foreach ($rows as $row) {
                $entityClone = clone $entity;
                self::assignEntityRecord($entityClone, $record);
                self::assignEntityProperties($entityClone, $row, $ecMeta);

                // Fill linked properties.
                foreach ($this->getLinkedProperties($ecMeta) as $epMeta) {
                    $this->loadLinkedProperty($epMeta, $entityClone, 'find');
                }

                $data[] = $entityClone;
            }

            // Create, fill & lock entity list.
            $entityList = $this->initEntityList($ecMeta->getListClass());
            $entityList->setData($data)->readOnly(true);

            $pager && $entityList->setPager($pager);

            return $entityList;
        }

        return null;
    }

    public function remove(object $entity, int|string $id = null): object
    {
        $ecMeta = MetaParser::parse($entity::class);

        $id   ??= self::getEntityPrimaryValue($entity, $ecMeta);
        $fields = self::getEntityFields($entity);

        // No try/catch, so allow exceptions in Record.
        $record = $this->initRecord($ecMeta)
                ->setId($id)
                ->return($fields)
                ->remove();

        self::assignEntityRecord($entity, $record);
        self::assignEntityProperties($entity, $record, $ecMeta);

        if ($record->isRemoved()) {
            // Drop linked properties (records actually).
            foreach ($this->getLinkedProperties($ecMeta) as $epMeta) {
                $this->unloadLinkedProperty($epMeta, $entity);
            }
        }

        return $entity;
    }

    public function removeBy(string $entityClass, string|array $where): object|null
    {
        $ecMeta = MetaParser::parse($entityClass);

        $entity = new $entityClass();
        $record = $this->initRecord($ecMeta);
        $return = self::getEntityFields($entity);

        $rows = $record->delete($where, return: $return);
        if ($rows != null) {
            foreach ($rows as $row) {
                $entityClone = clone $entity;
                self::assignEntityRecord($entityClone, $record);
                self::assignEntityProperties($entityClone, $row, $ecMeta);

                // Drop linked properties (records actually).
                foreach ($this->getLinkedProperties($ecMeta) as $epMeta) {
                    $this->unloadLinkedProperty($epMeta, $entityClone);
                }

                $data[] = $entityClone;
            }

            // Create, fill & lock entity list.
            $entityList = $this->initEntityList($ecMeta->getListClass());
            $entityList->setData($data)->readOnly(true);

            return $entityList;
        }

        return null;
    }

    private function initRecord(EntityClassMeta $ecMeta, object $entity = null): Record
    {
        $validations = null;

        // Assign validations if available.
        if ($entity != null) {
            $ref = $ecMeta->getReflector();
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
                foreach ($ecMeta->getProperties() as $name => $epMeta) {
                    // Skip entity properties.
                    if ($epMeta->hasEntity()) {
                        continue;
                    }

                    $validations[$name] = $epMeta->getValidation();
                }
            }
        }

        // Use annotated record class or default.
        $record = $ecMeta->getRecordClass() ?: Record::class;

        return new $record(
            $this->db,
            table: $ecMeta->getTable(),
            tablePrimary: ($id = $ecMeta->getTablePrimary()),
            options: [
                'transaction' => $ecMeta->getOption('transaction', true),
                'sequence'    => $ecMeta->getOption('sequence', !!$id),
                'validate'    => $ecMeta->getOption('validate', !!$validations),
            ],
            validations: $validations,
        );
    }

    private function initEntityList(string|null $class): AbstractEntityList
    {
        if ($class != null) {
            // Check class validity.
            if (!class_extends($class, AbstractEntityList::class)) {
                throw new ManagerException(
                    'Entity list class %s must extend %s',
                    [$class, AbstractEntityList::class]
                );
            }

            $entityList = new $class();
        } else {
            $entityList = new class() extends AbstractEntityList {};
        }

        return $entityList;
    }

    private function getLinkedProperties(EntityClassMeta $ecMeta): array
    {
        return array_filter($ecMeta->getProperties(), fn($p) => $p->isLinked());
    }

    private function loadLinkedProperty(EntityPropertyMeta $epMeta, object $entity, string $action = null): void
    {
        // Check whether cascade op allows given action.
        if ($action && !$epMeta->isLinkedCascadesFor($action)) {
            return;
        }

        $class = $epMeta->getEntityClass() ?: throw new ManagerException(
            'No valid link entity provided in `%s` meta',
            $epMeta->getName()
        );

        [$table, $column, $condition, $method, $limit] = $epMeta->packLinkStuff();

        // Check non-link / non-valid properties.
        ($table && $column) ?: throw new ManagerException(
            'No valid link table/column provided in `%s` meta',
            $epMeta->getName()
        );

        // Parse linked property class meta.
        $ecLinkedMeta = MetaParser::parse($class);

        // prd($class);
        // prd($epMeta->getClass());
        // prd($epMeta->getReflector()->getDeclaringClass()->name);
        // die;

        // Given or default limit (if not disabled as "-1").
        $limit = ($limit != -1) ? $limit : null;

        switch ($method) {
            case 'one-to-one':
                $primaryField = $ecLinkedMeta->getTablePrimary();
                $primaryValue = self::getPropertyValue($column, $entity);

                $limit = 1; // Update limit.
                break;
            case 'one-to-many':
                $primaryField = $column; // Reference.

                // Get value from property's class.
                // $epClassMeta  = MetaParser::parse($epMeta->getReflector()->getDeclaringClass()->name);
                $epClassMeta  = MetaParser::parse($epMeta->getClass());
                $primaryValue = self::getPropertyValue($epClassMeta->getTablePrimary(), $entity);

                unset($epClassMeta); // Free.
                break;
            case 'many-to-one':
                $primaryField = $ecLinkedMeta->getTablePrimary();
                $primaryValue = self::getPropertyValue($column, $entity);
                break;
            default:
                throw new ManagerException(
                    'Unimplemented link method `%s` on `%s` property',
                    [$method, $epMeta->getName()]
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
        $propEntityList = ($listClass = $epMeta->getEntityListClass()) ? new $listClass() : null;

        // Nope..
        // $propEntity->setOwner($entity);
        // $propEntityList?->setOwner($entity);

        // An entity list.
        if ($propEntityList != null) {
            $data = (array) $query->getArrayAll($pager, $limit);
            foreach ($data as $dat) {
                $propEntityClone = clone $propEntity;
                foreach ($dat as $name => $value) {
                    $prop = $ecLinkedMeta->getProperty($name);
                    $prop ? self::setPropertyValue($prop->getReflector(), $propEntityClone, $value)
                          : throw new ManagerException('Property `%s.%s` not exists or private', [$class, $name]);
                }

                $propEntityList->add($propEntityClone);
            }

            $pager && $propEntityList->setPager($pager);

            // Set property value as an entity list.
            self::setPropertyValue($epMeta->getReflector(), $entity, $propEntityList);
        }
        // An entity.
        else {
            $data = (array) $query->getArray();
            foreach ($data as $name => $value) {
                $prop = $ecLinkedMeta->getProperty($name);
                $prop ? self::setPropertyValue($prop->getReflector(), $propEntity, $value)
                      : throw new ManagerException('Property `%s.%s` not exists or private', [$class, $name]);
            }

            // Recursion for other linked stuff.
            foreach ($this->getLinkedProperties($ecLinkedMeta) as $prop) {
                $this->loadLinkedProperty($prop, $propEntity, $action);
            }

            // Set property value as an entity.
            self::setPropertyValue($epMeta->getReflector(), $entity, $propEntity);
        }
    }

    private function unloadLinkedProperty(EntityPropertyMeta $epMeta, object $entity): void
    {
        // Check whether cascade op allows remove action.
        if (!$epMeta->isLinkedCascadesFor('remove')) {
            return;
        }

        $class = $epMeta->getEntityClass() ?: throw new ManagerException(
            'No valid link entity provided in `%s` meta',
            $epMeta->getName()
        );

        [$table, $column, $condition, $method, $limit] = $epMeta->packLinkStuff();

        // Check non-link / non-valid properties.
        ($table && $column) ?: throw new ManagerException(
            'No valid link table/column provided in `%s` meta',
            $epMeta->getName()
        );

        $primaryField = MetaParser::parse($class)->getTablePrimary();
        $primaryValue = self::getPropertyValue($primaryField, $entity);

        // Create a delete query & apply link criteria.
        $this->db->initQuery($table)
                 ->delete()
                 ->equal($column, $primaryValue)
                 ->run();
    }

    private static function assignEntityRecord(object $entity, Record $record): void
    {
        // When entity extends AbstractEntity.
        if ($entity instanceof AbstractEntity) {
            $entity->setRecord($record);
        }
    }
    private static function assignEntityProperties(object $entity, array|Record $record, EntityClassMeta $ecMeta): void
    {
        $data = is_array($record) ? $record : $record->getData();

        if ($data) {
            $props = $ecMeta->getProperties();
            foreach ($data as $name => $value) {
                isset($props[$name]) && self::setPropertyValue(
                    $props[$name]->getReflector(), $entity, $value
                );
            }
        }
    }

    private static function setPropertyValue(string|ReflectionProperty $ref, object $entity, $value)
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

    private static function getEntityPrimaryValue(object $entity, EntityClassMeta $ecMeta): int|string|null
    {
        $primary = (string) $ecMeta->getTablePrimary();

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
