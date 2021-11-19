<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\Manager;
use froq\database\record\Record;
use froq\collection\ItemCollection;
use froq\pager\Pager;

/**
 * Abstract Entity List.
 *
 * Represents an abstract entity list class that can be extended by entity list classes used for
 * accessing & modifiying data via its utility methods such as saveAll(), findAll(), removeAll()
 * and checkers for these methods.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\AbstractEntityList
 * @author  Kerem Güneş
 * @since   5.0, Replaced with "object" subpackage.
 */
abstract class AbstractEntityList extends ItemCollection
{
    /** @var froq\database\entity\Manager */
    private Manager $manager;

    /** @var froq\pager\Pager|null */
    private Pager|null $pager = null;

    /**
     * Constructor.
     *
     * @param ... $entities
     */
    public function __construct(...$entities)
    {
        $entities && $this->resetData($entities);
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
        unset($data["\0{$class}\0pager"]);

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
     * Set pager property.
     *
     * @param  froq\pager\Pager $pager
     * @return self
     */
    public final function setPager(Pager $pager): self
    {
        $this->pager = $pager;

        return $this;
    }

    /**
     * Get manager property.
     *
     * @return froq\pager\Pager|null $pager
     */
    public final function getPager(): Pager|null
    {
        return $this->pager;
    }

    /**
     * Run a "save-all" action using manager.
     *
     * @return self
     */
    public final function saveAll(): self
    {
        $this->manager->saveAll($this);

        return $this;
    }

    /**
     * Run a "find-all" action using manager.
     *
     * @return self
     */
    public final function findAll(): self
    {
        $this->manager->findAll($this);

        return $this;
    }

    /**
     * Run a "remove-all" action using manager.
     *
     * @return self
     */
    public final function removeAll(): self
    {
        $this->manager->removeAll($this);

        return $this;
    }

    /**
     * Check for last "save-all" action result.
     *
     * @return bool
     */
    public final function isSavedAll(): bool
    {
        return !!array_filter($this->data, fn($entity) => $entity->isSaved());
    }

    /**
     * Check for last "find-all" action result.
     *
     * @return bool
     */
    public final function isFindedAll(): bool
    {
        return !!array_filter($this->data, fn($entity) => $entity->isFinded());
    }

    /**
     * Check for last "remove-all" action result.
     *
     * @return bool
     */
    public final function isRemovedAll(): bool
    {
        return !!array_filter($this->data, fn($entity) => $entity->isRemoved());
    }

    /**
     * @alias of isFindedAll()
     */
    public final function isFoundAll(): bool
    {
        return $this->isFindedAll();
    }
}
