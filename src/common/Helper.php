<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\common;

use froq\database\{Database, DatabaseException};

/**
 * @package froq\database\common
 * @object  froq\database\common\Helper
 * @author  Kerem Güneş
 * @since   6.0
 * @static
 */
final class Helper extends \StaticClass
{
    /**
     * Get active database.
     *
     * @param  string|null $method
     * @return froq\database\Database
     * @throws froq\database\DatabaseException
     */
    public static function getActiveDatabase(string $method = null): Database
    {
        if (function_exists('app')) {
            $database = app()->database;
        }

        return $database ?? throw new DatabaseException(
            'No database given to deal, be sure running this module with `froq\app` '.
            'module and `database` option exists in app config or pass $db argument '.
            'to %s()', $method ?? get_trace()[0]['callerMethod']
        );
    }
}
