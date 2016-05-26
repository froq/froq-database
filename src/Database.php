<?php
/**
 * Copyright (c) 2016 Kerem Güneş
 *     <k-gun@mail.com>
 *
 * GNU General Public License v3.0
 *     <http://www.gnu.org/licenses/gpl-3.0.txt>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace Froq\Database;

use Froq\Database\Vendor\{Vendor, VendorInterface, Mysql};

/**
 * @package    Froq
 * @subpackage Froq\Database
 * @object     Froq\Database\Database
 * @author     Kerem Güneş <k-gun@mail.com>
 */
final class Database
{
    /**
    * Vendors.
    * @const string
    */
    const VENDOR_MYSQL = 'mysql',
          VENDOR_COUCH = 'couch',
          VENDOR_MONGO = 'mongo';

    /**
     * Instances.
     * @var array
     */
    private static $instances = [];

    /**
     * Constructor.
     */
    final public function __construct()
    {}

    /**
     * Init.
     * @param  string $vendor
     * @return Froq\Database\Vendor\VendorInterface
     */
    final public static function init(string $vendor): VendorInterface
    {
        if (isset(self::$instances[$vendor])) {
            return self::$instances[$vendor];
        }

        $app = app();
        $cfg = $app->config->get('db');
        if (!isset($cfg[$vendor][$app->env])) {
            throw new DatabaseException("'{$vendor}' options not found in config!");
        }

        switch ($vendor) {
            // only mysql for now
            case self::VENDOR_MYSQL:
                $instance = Mysql::init($cfg[$vendor][$app->env]);
                break;
            default:
                throw new DatabaseException('Unimplemented vendor given!');
        }

        return (self::$instances[$vendor] = $instance);
    }

    /**
     * Create a MySQL worker instance.
     * @return Froq\Database\Vendor\Mysql
     */
    final public static function initMysql(): Mysql
    {
        return self::init(self::VENDOR_MYSQL);
    }
}
