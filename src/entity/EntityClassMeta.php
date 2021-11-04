<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\Meta;

/**
 * Entity Class Meta.
 *
 * Represents a metadata class entity that keeps a parsed class metadata details.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\EntityClassMeta
 * @author  Kerem Güneş
 * @since   5.0
 */
final class EntityClassMeta extends Meta
{
    /** @var array<froq\database\entity\EntityPropertyMeta> */
    private array $properties = [];

    /**
     * Set all properties.
     *
     * @param  array<froq\database\entity\EntityPropertyMeta> $properties
     * @return void
     */
    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    /**
     * Get all properties.
     *
     * @return array<froq\database\entity\EntityPropertyMeta|void>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Get a property by given name.
     *
     * @param  string $name
     * @return froq\database\entity\EntityPropertyMeta|null
     */
    public function getProperty(string $name): EntityPropertyMeta|null
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * Get all property names.
     *
     * @return array<string|void>
     */
    public function getPropertyNames(): array
    {
        return array_keys($this->properties);
    }

    /**
     * Check whether class metadata contains sequence option.
     *
     * @return bool
     */
    public function hasSequence(): bool
    {
        return (bool) $this->getOption('sequence', default: true);
    }

    /**
     * Get table option from class metadata.
     *
     * @return string|null
     */
    public function getTable(): string|null
    {
        return $this->getDataField('table');
    }

    /**
     * Get table primary option from class metadata.
     *
     * @return string|null
     */
    public function getTablePrimary(): string|null
    {
        $ret = $this->getDataField('id', default: 'id');

        // We use only one/first column (@fornow).
        if ($ret && strpos($ret, ',')) {
            $ret = split('\s*,\s*', $ret)[1];
        }

        return $ret;
    }

    /**
     * Get list class.
     *
     * @return string|null
     */
    public function getListClass(): string|null
    {
        return $this->getDataField('list');
    }

    /**
     * Get record class.
     *
     * @return string|null
     */
    public function getRecordClass(): string|null
    {
        return $this->getDataField('record');
    }

    /**
     * Pack table stuff.
     *
     * @return array
     * @internal
     */
    public function packTableStuff(): array
    {
        return [
            $this->getTable(),
            $this->getTablePrimary(),
        ];
    }
}
