<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\trait;

use froq\database\entity\EntityManager;

/**
 * A trait, provides `$em` property and its getter method.
 *
 * @package froq\database\trait
 * @object  froq\database\trait\EmTrait
 * @author  Kerem Güneş
 * @since   5.1
 */
trait EmTrait
{
    /** @var froq\database\entity\EntityManager */
    protected EntityManager $em;

    /**
     * Get em property.
     *
     * @return froq\database\entity\EntityManager
     */
    public final function em(): EntityManager
    {
        return $this->em;
    }
}
