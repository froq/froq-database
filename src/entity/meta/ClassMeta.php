<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity\meta;

/**
 * A metadata class, keeps parsed class metadata details.
 *
 * @package froq\database\entity\meta
 * @object  froq\database\entity\meta\ClassMeta
 * @author  Kerem Güneş
 * @since   5.0
 */
final class ClassMeta extends Meta
{
    /** @var array<froq\database\entity\meta\PropertyMeta> */
    private array $properties = [];

    /**
     * Constructor.
     *
     * @param string     $class
     * @param array|null $data
     */
    public function __construct(string $class, array $data = null)
    {
        parent::__construct(parent::TYPE_CLASS, $class, $class, $data);
    }

    /**
     * Set all properties.
     *
     * @param  array<froq\database\entity\meta\PropertyMeta> $properties
     * @return void
     */
    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    /**
     * Get all properties.
     *
     * @return array<froq\database\entity\meta\PropertyMeta|null>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Add a property (meta).
     *
     * @param  string                                 $name
     * @param  froq\database\entity\meta\PropertyMeta $meta
     * @return void
     */
    public function addProperty(string $name, PropertyMeta $meta): void
    {
        $this->properties[$name] = $meta;
    }

    /**
     * Get a property (meta).
     *
     * @param  string $name
     * @return froq\database\entity\meta\PropertyMeta|null
     */
    public function getProperty(string $name): PropertyMeta|null
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
        return $this->getDataField('primary', default: 'id');
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
