<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\trait;

use froq\database\entity\EntityManager;

/**
 * A trait, provides `$em` property and its getter method.
 *
 * @package froq\database\trait
 * @class   froq\database\trait\EmTrait
 * @author  Kerem Güneş
 * @since   5.1
 */
trait EmTrait
{
    /** Manager instance. */
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
