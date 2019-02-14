<?php
/**
 * MIT License <https://opensource.org/licenses/mit>
 *
 * Copyright (c) 2015 Kerem Güneş
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
declare(strict_types=1);

namespace froq\database;

use froq\App;
use froq\database\vendor\{VendorInterface, Oppa};

/**
 * Database.
 * @package froq\database
 * @object  froq\database\Database
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   1.0
 */
final class Database
{
    /**
    * Vendors names.
    * @const string
    */
    public const VENDOR_NAME_MYSQL = 'mysql',
                 VENDOR_NAME_PGSQL = 'pgsql';

    /**
     * App.
     * @var froq\App
     */
    private $app;

    /**
     * Instances.
     * @var array
     */
    private static $instances = [];

    /**
     * Constructor.
     * @param froq\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Get app.
     * @return froq\App
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
     * @return froq\database\vendor\VendorInterface
     * @throws froq\database\DatabaseException
     */
    public function init(string $vendorName): VendorInterface
    {
        $vendorName = strtolower($vendorName);
        if (!isset(self::$instances[$vendorName])) {
            $appEnv = $this->app->env();
            $appConfig = $this->app->config();

            @ $cfg = $appConfig['db'];
            if ($cfg == null) {
                throw new DatabaseException("Config error, no 'db' options found");
            }

            if (!isset($cfg[$vendorName][$appEnv])) {
                throw new DatabaseException("Config error, '{$vendorName}' options not found for '{$appEnv}'");
            }

            switch ($vendorName) {
                // only mysql & pgsql for now
                case self::VENDOR_NAME_MYSQL:
                case self::VENDOR_NAME_PGSQL:
                    self::$instances[$vendorName] = Oppa::init($cfg[$vendorName][$appEnv]);
                    break;
                default:
                    throw new DatabaseException("Unimplemented vendor name '{$vendorName}' given");
            }
        }

        return self::$instances[$vendorName];
    }
}
