<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity\proxy;

use froq\database\entity\EntityManager;

/**
 *  A proxy class, provides isolation for entity & entity list classes.
 *
 * @package froq\database\entity\proxy
 * @object  froq\database\entity\proxy\Proxy
 * @author  Kerem Güneş
 * @since   6.0
 * @internal
 */
abstract class Proxy
{
    /** @var froq\database\entity\EntityManager */
    protected EntityManager $manager;

    /**
     * Constructor.
     *
     * @param froq\database\entity\EntityManager|null $manager
     */
    public function __construct(EntityManager $manager = null)
    {
        $this->manager = $manager ?? new EntityManager();
    }

    /**
     * Set manager.
     *
     * @param  froq\database\entity\EntityManager $manager
     * @return void
     */
    public function setManager(EntityManager $manager): void
    {
        $this->manager = $manager;
    }

    /**
     * Get manager.
     *
     * @return froq\database\entity\EntityManager
     */
    public function getManager(): EntityManager
    {
        return $this->manager;
    }
}
