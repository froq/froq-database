<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\entity\meta;

/**
 * A metadata class, keeps parsed property metadata details.
 *
 * @package froq\database\entity\meta
 * @class   froq\database\entity\meta\PropertyMeta
 * @author  Kerem Güneş
 * @since   5.0
 */
class PropertyMeta extends Meta
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
     * Check whether property is a field.
     *
     * @return bool
     */
    public function isField(): bool
    {
        return $this->hasDataItem('field');
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
     * Check if this property is marked as "primary" field for a table.
     *
     * @return bool
     */
    public function isPrimary(): bool
    {
        return $this->getOption('primary', default: false);
    }

    /**
     * Check if this property is marked as "hidden" field for select/returning operations.
     *
     * @return bool
     */
    public function isHidden(): bool
    {
        return $this->getOption('hidden', default: false);
    }
}
