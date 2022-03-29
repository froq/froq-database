<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

/**
 * A metadata class, keeps parsed class metadata details.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\ClassMeta
 * @author  Kerem Güneş
 * @since   5.0
 */
final class ClassMeta extends Meta
{
    /** @var array<froq\database\entity\PropertyMeta> */
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
     * @param  array<froq\database\entity\PropertyMeta> $properties
     * @return void
     */
    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    /**
     * Get all properties.
     *
     * @return array<froq\database\entity\PropertyMeta|null>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Get a property by given name.
     *
     * @param  string $name
     * @return froq\database\entity\PropertyMeta|null
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
        return $this->getDataField('primary');
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
