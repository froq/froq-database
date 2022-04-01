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
 * @static @internal
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
        // Try to use app's database.
        if (function_exists('app')) {
            return app()->database();
        }

        $method ??= get_trace()[0]['callerMethod'];

        return function_exists('app') ? app()->database() : throw new DatabaseException(
            'No database given to deal, be sure running this module with `froq\app` '.
            'module and `database` option exists in app config or pass $db argument '.
            'to %s()', $method
        );
    }
}
