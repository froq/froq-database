<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\Manager;
use froq\database\record\Record;

/**
 * Abstract Entity.
 *
 * Represents an abstract entity class that may be extended by entity classes used for
 * accessing & modifiying data via its utility methods such as save(), find(), remove()
 * and checkers for these methods.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\AbstractEntity
 * @author  Kerem Güneş
 * @since   5.0, Replaced with "object" subpackage.
 */
abstract class AbstractEntity
{
    /** @var froq\database\entity\Manager */
    private Manager $manager;

    /** @var froq\database\record\Record */
    private Record $record;

    /**
     * Constructor.
     *
     * @param ... $properties
     */
    public function __construct(...$properties)
    {
        $properties && $this->fill(...$properties);
    }

    /**
     * Magic - debug info.
     *
     * @return array
     */
    public function __debugInfo()
    {
        [$data, $class] = [(array) $this, self::class];

        // Drop internals.
        unset($data["\0{$class}\0manager"]);
        unset($data["\0{$class}\0record"]);

        return $data;
    }

    /**
     * Set manager property.
     *
     * @param  froq\database\entity\Manager $manager
     * @return self
     */
    public final function setManager(Manager $manager): self
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * Get manager property.
     *
     * @return froq\database\entity\Manager|null $manager
     */
    public final function getManager(): Manager|null
    {
        return $this->manager ?? null;
    }

    /**
     * Set record property.
     *
     * @param  froq\database\record\Record $record
     * @return self
     */
    public final function setRecord(Record $record): self
    {
        $this->record = $record;

        return $this;
    }

    /**
     * Get record property.
     *
     * @return froq\database\record\Record|null $record
     */
    public final function getRecord(): Record|null
    {
        return $this->record ?? null;
    }

    /**
     * Run a "save" action using manager.
     *
     * @return self.
     */
    public final function save(): self
    {
        $this->manager->save($this);

        return $this;
    }

    /**
     * Run a "find" action using manager.
     *
     * @return self.
     */
    public final function find(): self
    {
        $this->manager->find($this);

        return $this;
    }

    /**
     * Run a "remove" action using manager.
     *
     * @return self.
     */
    public final function remove(): self
    {
        $this->manager->remove($this);

        return $this;
    }

    /**
     * Check for last "save" action result.
     *
     * @return bool
     */
    public final function isSaved(): bool
    {
        return $this->record->isSaved();
    }

    /**
     * Check for last "find" action result.
     *
     * @return bool
     */
    public final function isFinded(): bool
    {
        return $this->record->isFinded();
    }

    /**
     * Check for last "remove" action result.
     *
     * @return bool
     */
    public final function isRemoved(): bool
    {
        return $this->record->isRemoved();
    }

    /**
     * Fill the entity object with given properties.
     *
     * @param  ... $properties
     * @return self
     */
    public final function fill(...$properties): self
    {
        foreach ($properties as $name => $value) {
            if (property_exists(static::class, $name)) {
                $this->$name = $value;
            }
        }

        return $this;
    }

    /**
     * @alias of isFinded()
     */
    public final function isFound(): bool
    {
        return $this->isFinded();
    }
}
