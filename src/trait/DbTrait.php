<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\trait;

use froq\database\Database;

/**
 * A trait, provides a read-only `$db` property and its getter method.
 *
 * @package froq\database\trait
 * @object  froq\database\trait\DbTrait
 * @author  Kerem Güneş
 * @since   5.0
 */
trait DbTrait
{
    /** @var froq\database\Database */
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
