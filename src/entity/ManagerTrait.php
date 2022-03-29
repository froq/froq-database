<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

/**
 * A trait, used by `Entity` & `EntityList` classes internally.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\ManagerTrait
 * @author  Kerem Güneş
 * @since   6.0
 * @internal
 */
trait ManagerTrait
{
    /** @var froq\database\entity\Manager */
    private Manager $manager;

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
     * @return froq\database\entity\Manager|null
     */
    public final function getManager(): Manager|null
    {
        return $this->manager ?? null;
    }
}
