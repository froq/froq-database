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
        $cmeta = MetaParser::parse($entity::class);

        $entityData = $entityProps = [];
        foreach ($cmeta->getProperties() as $name => $pmeta) {
            $value = self::getPropertyValue($pmeta->getReflector(), $entity);

            // Collect & skip entity properties to save later.
            if ($pmeta->isEntity()) {
                // We can't save empty entities.
                if ($value != null) {
                    $entityProps[] = $value;
                }
                continue;
            }

            $entityData[$name] = $value ?? $pmeta->getValidationDefault();
        }

        // No try/catch, so allow exceptions in Record.
        $record = $this->initRecord($cmeta, $entity)
                ->setData($entityData)
                ->save();

        self::assignEntityProps($entity, $record, $cmeta);
        self::assignEntityRecord($entity, $record);

        if ($record->isSaved()) {
            // Also save if any entity property exists.
            foreach ($entityProps as $entityProp) {
                $this->save($entityProp);
            }

            // Fill linked properties.
            foreach ($this->getLinkedProps($cmeta) as $pmeta) {
                $this->loadLinkedProp($pmeta, $entity, 'save');
            }
        }

        return $entity;
    }

    public function find(object $entity, int|string $id = null): object
    {
        $cmeta = MetaParser::parse($entity::class);

        // Get from entity.
        if ($id === null) {
            $id = self::getEntityPrimaryValue($entity, $cmeta);
        }

        $fields = self::getEntityFields($entity);

        // No try/catch, so allow exceptions in Record.
        $record = $this->initRecord($cmeta)
                ->setId($id)
                ->return($fields)
                ->find();

        self::assignEntityProps($entity, $record, $cmeta);
        self::assignEntityRecord($entity, $record);

        if ($record->isFinded()) {
            // Fill linked properties.
            foreach ($this->getLinkedProps($cmeta) as $pmeta) {
                $this->loadLinkedProp($pmeta, $entity, 'find');
            }
        }

        return $entity;
    }

    public function findBy(string $entityClass, string|array $where = null, int $limit = null, string $order = null,
        Pager &$pager = null): array|object
    {
        $cmeta = MetaParser::parse($entityClass);

        $entity = new $entityClass();
        $entityList = [];

        $record = $this->initRecord($cmeta);
        $fields = self::getEntityFields($entity);

        $rows = $record->select($where ?? [], fields: $fields, limit: $limit, order: $order);

        if ($rows != null) {
            // For a proper list to loop below.
            if ($limit == 1) {
                $rows = [$rows];
            }

            foreach ($rows as $row) {
                $entityClone = clone $entity;
                self::assignEntityProps($entityClone, $row, $cmeta);
                self::assignEntityRecord($entityClone, $record);

                // Fill linked properties.
                foreach ($this->getLinkedProps($cmeta) as $pmeta) {
                    $this->loadLinkedProp($pmeta, $entityClone, 'find');
                }

                $entityList[] = $entityClone;
            }

            // When entity list provided.
            if (($entityListClass = $cmeta->getListClass())
                && class_extends($entityListClass, AbstractEntityList::class)) {
                $entityListObject = new $entityListClass();
                $entityListObject->setData($entityList);

                // Lock entity list & set pager.
                $entityListObject->readOnly(true);
                $pager && $entityListObject->setPager($pager);

                $entityList = $entityListObject;
            }
        }

        return $entityList;
    }

    public function remove() {}
    public function removeBy() {}

    private function initRecord(EntityClassMeta $cmeta, object $entity = null): Record
    {
        $validations = null;
        if ($entity != null) {
            $ref = $cmeta->getReflector();
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
                foreach ($cmeta->getProperties() as $name => $pmeta) {
                    // Skip entity properties.
                    if ($pmeta->isEntity()) {
                        continue;
                    }

                    $validations[$name] = $pmeta->getValidation();
                }
            }
        }

        // Use annotated record class or default.
        $record = $cmeta->getRecordClass() ?: Record::class;

        return new $record(
            $this->db,
            table: $cmeta->getTable(),
            tablePrimary: ($id = $cmeta->getTablePrimary()),
            options: [
                'transaction' => $cmeta->getOption('transaction', true),
                'sequence'    => $cmeta->getOption('sequence', !!$id),
                'validate'    => $cmeta->getOption('validate', !!$validations),
            ],
            validations: $validations,
        );
    }

    private function getLinkedProps(EntityClassMeta $cmeta): array
    {
        return array_filter($cmeta->getProperties(), fn($p) => $p->isLink());
    }

    private function loadLinkedProp(EntityPropertyMeta $pmeta, object $entity, string $action = null): void
    {
        // Check whether cascade op allows the given action.
        if ($action && !$pmeta->isLinkCascadingFor($action)) {
            return;
        }

        $class = $pmeta->getEntityClass() ?: throw new ManagerException(
            'No valid link entity provided in `%s` meta',
            $pmeta->getName()
        );

        [$table, $column, $condition, $method, $limit] = $pmeta->packLinkStuff();

        // Check non-link / non-valid properties.
        $column ?: throw new ManagerException(
            'No valid link column provided in `%s` meta',
            $pmeta->getName()
        );

        $pcmeta = MetaParser::parse($class);
        $fields = self::getEntityFields($class);

        // Create a select query.
        $query = $this->db->initQuery();
        $query->select($fields)->from($table);

        // Given or default limit (if not disabled as "-1").
        $limit = ($limit != -1) ? $limit : null;

        switch ($method) {
            case 'one-to-one':
                $pfield = $pcmeta->getTablePrimary();
                $pvalue = self::getPropertyValue($column, $entity);

                $limit = 1; // Update limit.
                break;
            case 'one-to-many':
                $pfield = $column; // Reference.
                $pdmeta = MetaParser::parse($pmeta->getReflector()->getDeclaringClass()->name);
                $pvalue = self::getPropertyValue($pdmeta->getTablePrimary(), $entity);

                unset($pdmeta); // Free.
                break;
            case 'many-to-one':
                $pfield = $pcmeta->getTablePrimary();
                $pvalue = self::getPropertyValue($column, $entity);
                break;
            default:
                throw new ManagerException(
                    'Unimplemented link method `%s` on `%s` property',
                    [$method, $pmeta->getName()]
                );
        }

        // Apply link criteria.
        $query->equal($pfield, $pvalue);

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
        $propEntityList = ($listClass = $pmeta->getEntityListClass()) ? new $listClass() : null;

        // Nope..
        // $propEntity->setOwner($entity);
        // $propEntityList?->setOwner($entity);

        // An entity list.
        if ($propEntityList != null) {
            $data = (array) $query->getArrayAll($pager, $limit);
            foreach ($data as $dat) {
                $propEntityClone = clone $propEntity;
                foreach ($dat as $name => $value) {
                    $prop = $pcmeta->getProperty($name);
                    $prop ? self::setPropertyValue($prop->getReflector(), $propEntityClone, $value)
                          : throw new ManagerException('Property `%s.%s` not exists or private', [$class, $name]);
                }

                $propEntityList->add($propEntityClone);
            }

            $pager && $propEntityList->setPager($pager);

            // Set property value as an entity list.
            self::setPropertyValue($pmeta->getReflector(), $entity, $propEntityList);
        }
        // An entity.
        else {
            $data = (array) $query->getArray();
            foreach ($data as $name => $value) {
                $prop = $pcmeta->getProperty($name);
                $prop ? self::setPropertyValue($prop->getReflector(), $propEntity, $value)
                      : throw new ManagerException('Property `%s.%s` not exists or private', [$class, $name]);
            }

            // Recursion for other linked stuff.
            foreach ($this->getLinkedProps($pcmeta) as $prop) {
                $this->loadLinkedProp($prop, $propEntity, $action);
            }

            // Set property value as an entity.
            self::setPropertyValue($pmeta->getReflector(), $entity, $propEntity);
        }
    }

    private static function assignEntityProps(object $entity, array|Record $record, EntityClassMeta $cmeta): void
    {
        $data = is_array($record) ? $record : $record->getData();

        if ($data) {
            $props = $cmeta->getProperties();
            foreach ($data as $name => $value) {
                isset($props[$name]) && self::setPropertyValue(
                    $props[$name]->getReflector(), $entity, $value
                );
            }
        }
    }
    private static function assignEntityRecord(object $entity, Record $record): void
    {
        // When entity extends AbstractEntity.
        if ($entity instanceof AbstractEntity) {
            $entity->setRecord($record);
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

        // When fields() method available.
        if (is_callable_method($entity, 'fields')) {
            $fields = $entity::fields();
            is_array($fields) || is_string($fields) || throw new ManagerException(
                'Method %s.fields() must return array|string, %s returned',
                [is_object($entity) ? $entity::class : $entity, get_type($fields)]
            );

            if (!$fields || $fields === ['*']) {
                $fields = '*';
            }
        }

        return $fields;
    }

    private static function getEntityPrimaryValue(object $entity, EntityClassMeta $cmeta): int|string|null
    {
        $primary = (string) $cmeta->getTablePrimary();

        if (isset($entity->{$primary})) {
            return $entity->{$primary};
        } elseif (is_callable_method($entity, 'getId')) {
            return $entity->getId();
        }

        return null;
    }
}
