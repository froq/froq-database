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

use Froq\App;
use Froq\Database\Vendor\{VendorInterface, Oppa};

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
    const VENDOR_NAME_MYSQL = 'mysql',
          VENDOR_NAME_PGSQL = 'pgsql';

    /**
     * App.
     * @var Froq\App
     */
    private $app;

    /**
     * Instances.
     * @var array
     */
    private static $instances = [];

    /**
     * Constructor.
     * @param Froq\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Get app.
     * @return Froq\App
     */
    public function getApp(): App
    {
        return $this->app;
    }

    /**
     * Get instances.
     * @return array
     */
    public function getInstances(): array
    {
        return self::$instances;
    }

    /**
     * Init.
     * @param  string $vendorName
     * @return Froq\Database\Vendor\VendorInterface
     */
    public function init(string $vendorName): VendorInterface
    {
        $vendorName = strtolower($vendorName);
        if (!isset(self::$instances[$vendorName])) {
            $appEnv = $this->app->env();
            $appConfig = $this->app->config();

            $cfg = $appConfig['db'];
            if (!isset($cfg[$vendorName][$appEnv])) {
                throw new DatabaseException("'{$vendorName}' options not found for '{$appEnv}' env in config!");
            }

            switch ($vendorName) {
                // only mysql & pgsql for now
                case self::VENDOR_NAME_MYSQL:
                case self::VENDOR_NAME_PGSQL:
                    self::$instances[$vendorName] = Oppa::init($cfg[$vendorName][$appEnv]);
                    break;
                default:
                    throw new DatabaseException("Unimplemented vendor name '{$vendorName}' given!");
            }
        }

        return self::$instances[$vendorName];
    }
}
