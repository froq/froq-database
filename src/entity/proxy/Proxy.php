<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity\proxy;

use froq\database\entity\Manager;

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
    /** @var froq\database\entity\Manager */
    protected Manager $manager;

    /**
     * Constructor.
     *
     * @param froq\database\entity\Manager|null $manager
     */
    public function __construct(Manager $manager = null)
    {
        $this->manager = $manager ?? new Manager();
    }

    /**
     * Set manager.
     *
     * @param  froq\database\entity\Manager $manager
     * @return void
     */
    public final function setManager(Manager $manager): void
    {
        $this->manager = $manager;
    }

    /**
     * Get manager.
     *
     * @return froq\database\entity\Manager
     */
    public final function getManager(): Manager
    {
        return $this->manager;
    }
}
