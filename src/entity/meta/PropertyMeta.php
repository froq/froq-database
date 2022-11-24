<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity\meta;

/**
 * A metadata class, keeps parsed property metadata details.
 *
 * @package froq\database\entity\meta
 * @object  froq\database\entity\meta\PropertyMeta
 * @author  Kerem Güneş
 * @since   5.0
 */
final class PropertyMeta extends Meta
{
    /**
     * Constructor.
     *
     * @param string     $name
     * @param string     $class
     * @param array|null $data
     */
    public function __construct(string $name, string $class, array $data = null)
    {
        parent::__construct(parent::TYPE_PROPERTY, $name, $class, $data);
    }

    /**
     * Get field name.
     *
     * @return string|null
     */
    public function getField(): string|null
    {
        return $this->getDataItem('field');
    }

    /**
     * Get field type.
     *
     * @return string|null
     */
    public function getFieldType(): string|null
    {
        return $this->getDataItem('fieldType');
    }

    /**
     * Get property value using reflection.
     *
     * @return mixed|null
     */
    public function getValue(object $object): mixed
    {
        return $this->getReflection()->getValue($object);
    }

    /**
     * Get validation options.
     *
     * @return array|null
     */
    public function getValidation(): array|null
    {
        return $this->getDataItem('validation');
    }

    /**
     * Get validation default option for empty/null states.
     *
     * @return mixed|null
     */
    public function getValidationDefault(): mixed
    {
        return $this->getDataItem('validation.default');
    }

    /**
     * Check whether property has entity class.
     *
     * @return bool
     */
    public function hasEntityClass(): bool
    {
        return $this->hasDataItem('entity');
    }

    /**
     * Check whether property has entity list class.
     *
     * @return bool
     */
    public function hasEntityListClass(): bool
    {
        return $this->hasDataItem('entityList');
    }

    /**
     * Get entity class.
     *
     * @return string|null
     */
    public function getEntityClass(): string|null
    {
        return $this->getDataItem('entity');
    }

    /**
     * Get entity list class.
     *
     * @return string|null
     */
    public function getEntityListClass(): string|null
    {
        return $this->getDataItem('entityList');
    }

    /**
     * Check whether this property is marked as "primary" field.
     *
     * @return bool
     */
    public function isPrimary(): bool
    {
        return $this->getOption('primary', default: false);
    }
}