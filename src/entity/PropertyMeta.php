<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

/**
 * A metadata class, keeps parsed property metadata details.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\PropertyMeta
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
     * Get entity class.
     *
     * @return string|null
     */
    public function getEntityClass(): string|null
    {
        return $this->getDataField('entity');
    }

    /**
     * Get entity list class.
     *
     * @return string|null
     */
    public function getEntityListClass(): string|null
    {
        return $this->getDataField('entityList');
    }

    /**
     * Get field name.
     *
     * @return string|null
     */
    public function getField(): string|null
    {
        return $this->getDataField('field');
    }

    /**
     * Get propert value using reflection.
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
        return $this->getDataField('validation');
    }

    /**
     * Get validation default option for empty/null situations.
     *
     * @return mixed|null
     */
    public function getValidationDefault(): mixed
    {
        return $this->getDataField('validation.default');
    }

    /**
     * Check whether property has entity class.
     *
     * @return bool
     */
    public function hasEntity(): bool
    {
        return !empty($this->data['entity']);
    }

    /**
     * Check whether property has entity list class.
     *
     * @return bool
     */
    public function hasEntityList(): bool
    {
        return !empty($this->data['entityList']);
    }
}
