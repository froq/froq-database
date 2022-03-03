<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\trait;

use froq\database\entity\Manager as EntityManager;

/**
 * Entity Manager Trait.
 *
 * A trait, provides a read-only `$em` property and its getter method.
 *
 * @package froq\database\trait
 * @object  froq\database\trait\EntityManagerTrait
 * @author  Kerem Güneş
 * @since   5.1
 */
trait EntityManagerTrait
{
    /** @var froq\database\entity\Manager */
    protected EntityManager $em;

    /**
     * Get em property.
     *
     * @return froq\database\entity\Manager
     */
    public final function em(): EntityManager
    {
        return $this->em;
    }
}
