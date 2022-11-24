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
    private array $propertyMetas = [];

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
     * Set property metas.
     *
     * @param  array<froq\database\entity\meta\PropertyMeta> $propertyMetas
     * @return void
     */
    public function setPropertyMetas(array $propertyMetas): void
    {
        $this->propertyMetas = $propertyMetas;
    }

    /**
     * Get property metas.
     *
     * @return array<froq\database\entity\meta\PropertyMeta>
     */
    public function getPropertyMetas(): array
    {
        return $this->propertyMetas;
    }

    /**
     * Add property meta.
     *
     * @param  string                                 $name
     * @param  froq\database\entity\meta\PropertyMeta $meta
     * @return void
     */
    public function addPropertyMeta(string $name, PropertyMeta $meta): void
    {
        $this->propertyMetas[$name] = $meta;
    }

    /**
     * Get property meta.
     *
     * @param  string $name
     * @return froq\database\entity\meta\PropertyMeta|null
     */
    public function getPropertyMeta(string $name): PropertyMeta|null
    {
        return $this->propertyMetas[$name] ??
            // Try with "field" definition.
            array_find($this->propertyMetas, fn(PropertyMeta $propertyMeta): bool => (
                $propertyMeta->getField() === $name
            ));
    }

    /**
     * Get table option from class metadata.
     *
     * @return string|null
     */
    public function getTable(): string|null
    {
        return $this->getDataItem('table');
    }

    /**
     * Get table primary option from class metadata.
     *
     * @return string|null
     */
    public function getTablePrimary(): string|null
    {
        return $this->getDataItem('primary', default: 'id') ??
            // Try with "primary" definition.
            array_find($this->propertyMetas, fn(PropertyMeta $propertyMeta): bool =>
                $propertyMeta->isPrimary() === true
            )?->getField();
    }

    /**
     * Get list class.
     *
     * @return string|null
     */
    public function getListClass(): string|null
    {
        return $this->getDataItem('list');
    }

    /**
     * Get record class.
     *
     * @return string|null
     */
    public function getRecordClass(): string|null
    {
        return $this->getDataItem('record');
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
