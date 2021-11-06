<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\trait;

use froq\database\entity\Manager;

/**
 * Entity Manager Trait.
 *
 * Represents a trait entity that holds a read-only `$em` property and its getter method.
 *
 * @package froq\database\trait
 * @object  froq\database\trait\EntityManagerTrait
 * @author  Kerem Güneş
 * @since   5.2
 */
trait EntityManagerTrait
{
    /** @var froq\database\entity\Manager */
    protected Manager $em;

    /**
     * Get em property.
     *
     * @return froq\database\entity\Manager
     */
    public final function em(): Manager
    {
        return $this->em;
    }
}
