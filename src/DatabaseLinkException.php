<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\DatabaseException;

/**
 * Database Link Exception.
 *
 * @package froq\database
 * @object  froq\database\DatabaseLinkException
 * @author  Kerem Güneş
 * @since   4.0, 5.0 Replaced with DatabaseConnectionException.
 */
class DatabaseLinkException extends DatabaseException
{}
