<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\trait;

use froq\database\Database;

/**
 * A trait, provides `$db` property and its getter method.
 *
 * @package froq\database\trait
 * @class   froq\database\trait\DbTrait
 * @author  Kerem Güneş
 * @since   5.0
 */
trait DbTrait
{
    /** Database instance. */
    protected Database $db;

    /**
     * Get db property.
     *
     * @return froq\database\Database
     */
    public final function db(): Database
    {
        return $this->db;
    }
}
