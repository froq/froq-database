<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{ManagerException, MetaParser, AbstractEntity};
use froq\database\record\{Form, Record};
use froq\database\{Database, Query, Result, trait\DbTrait};
use froq\validation\Rule as ValidationRule;
use ReflectionClass, ReflectionProperty, Throwable;

final class Manager
{
    /** @see froq\database\trait\DbTrait */
    use DbTrait;

    public function __construct(Database $db = null)
    {
        // Try to use active app database object.
        $db ??= function_exists('app') ? app()->database() : null;

        $db || throw new ManagerException(
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

    public function save(object $entity): object|null
    {
        $cmeta = MetaParser::parse($entity::class);

        $data = [];
        foreach ($cmeta->getProperties() as $name => $pmeta) {
            // Skip entity properties.
            if ($pmeta->isEntity()) {
                continue;
            }

            $data[$name] = self::getPropertyValue($pmeta->getReflector(), $entity);
        }

        $record = $this->initRecord($cmeta, $entity)
                ->setData($data)
                ->save(); // No try/catch, allow exceptions in Record.

        self::assignEntityProps($entity, $record, $cmeta);
        self::assignEntityRecord($entity, $record);

        // Fill linked properties.
        if ($record->isSaved()) {
            foreach ($this->getLinkedProps($cmeta) as $pmeta) {
                $this->loadLinkedProp($pmeta, $entity, 'save');
            }
        }

        return $entity;
    }

    public function find(object $entity, int|string $id = null): object|null
    {
        $cmeta = MetaParser::parse($entity::class);

        if ($id === null) {
            $id = (string) $id;
            $primary = $cmeta->getTablePrimary();
            if (isset($entity->$primary)) {
                $id = $entity->$primary;
            } elseif (is_callable_method($entity, 'getId')) {
                $id = $entity->getId();
            }
        }

        $fields = '*';
        // Get select fields if available.
        if (is_callable_method($entity, 'fields')) {
            $fields = $entity::fields();
        }

        $record = $this->initRecord($cmeta)
                ->setId($id)
                ->return($fields)
                ->find(); // No try/catch, allow exceptions in Record.

        self::assignEntityProps($entity, $record, $cmeta);
        self::assignEntityRecord($entity, $record);

        // Fill linked properties.
        if ($record->isFinded()) {
            foreach ($this->getLinkedProps($cmeta) as $pmeta) {
                $this->loadLinkedProp($pmeta, $entity, 'find');
            }
        }

        return $entity;
    }

    public function findBy() {}
    public function remove() {}
    public function removeBy() {}

    private function initRecord(EntityClassMeta $cmeta, object $entity = null): Record
    {
        $record = $cmeta->getRecord() ?: Record::class;

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

        return new $record(
            $this->db,
            table: $cmeta->getTable(),
            tablePrimary: $id = $cmeta->getTablePrimary(),
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
        if ($action != null && !$pmeta->isLinkCascadingFor($action)) {
            return;
        }

        [$table, $column, $condition, $method, $limit] = $pmeta->packLinkStuff();

        $class = $pmeta->getEntityClass();
        $class ?: throw new ManagerException(
            'No valid link entity provided in `%s` meta',
            $pmeta->getName()
        );

        // Check non-link / non-valid properties.
        $column ?: throw new ManagerException(
            'No valid link column provided in `%s` meta',
            $pmeta->getName()
        );

        $pcmeta = MetaParser::parse($class);

        $fields = '*';
        // Get select fields if available.
        if (is_callable_method($class, 'fields')) {
            $fields = $class::fields();
        }

        // Create a select query.
        $query = $this->db->initQuery();
        $query->select($fields)->from($table);

        // Given or default limit (if not disabled as "-1").
        $limit = ($limit != -1) ? ($limit ?? 1000) : null;

        switch ($method) {
            case 'one-to-one':
                $pfield = $pcmeta->getTablePrimary();
                $pvalue = self::getPropertyValue($column, $entity);

                // Update limit.
                $limit = 1;
                break;
            case 'one-to-many':
                $pfield = $column; // Reference column.
                $pdmeta = MetaParser::parse($pmeta->getReflector()->getDeclaringClass()->name);
                $pvalue = self::getPropertyValue($pdmeta->getTablePrimary(), $entity);

                unset($pdmeta);
                break;
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

        prs($query->toString(1));
        prs("---");

        $propEntity     = new $class();
        $propEntityList = ($listClass = $pmeta->getEntityListClass()) ? new $listClass() : null;

        // $propEntity->setOwner($entity);
        // $propEntityList?->setOwner($entity);

        // An entity list.
        if ($propEntityList != null) {
            $data = (array) $query->getArrayAll($pager, $limit);
            foreach ($data as $dat) {
                $propEntityClone = clone $propEntity;
                foreach ($dat as $name => $value) {
                    $prop = $pcmeta->getProperty($name);
                    $prop && self::setPropertyValue($prop->getReflector(), $propEntityClone, $value);
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
                $prop && self::setPropertyValue($prop->getReflector(), $propEntity, $value);
            }

            // Recursion for other linked stuff.
            foreach ($this->getLinkedProps($pcmeta) as $prop) {
                $this->loadLinkedProp($prop, $propEntity, $action);
            }

            // Set property value as an entity.
            self::setPropertyValue($pmeta->getReflector(), $entity, $propEntity);
        }
    }

    private static function assignEntityProps(object $entity, Record $record, EntityClassMeta $cmeta): void
    {
        if (!$record->isEmpty()) {
            $props = $cmeta->getProperties();
            foreach ($record->getData() as $name => $value) {
                isset($props[$name]) && self::setPropertyValue($props[$name]->getReflector(), $entity, $value);
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
}
