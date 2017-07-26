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

namespace Froq\Database\Vendor;

use Froq\Util\Traits\SingleTrait;

/**
 * @package    Froq
 * @subpackage Froq\Database
 * @object     Froq\Database\Vendor\Vendor
 * @author     Kerem Güneş <k-gun@mail.com>
 */
abstract class Vendor implements VendorInterface
{
    /**
     * Single.
     * @object Froq\Util\Traits\SingleTrait
     */
    use SingleTrait;

    /**
     * Database.
     * @var Froq\Database
     */
    protected $database;

    /**
     * @inheritDoc Froq\Database\Vendor\VendorInterface
     */
    public final function getDatabase()
    {
        return $this->database;
    }

    /**
     * @inheritDoc Froq\Database\Vendor\VendorInterface
     */
    public final function __call(string $method, array $methodArguments)
    {
        return call_user_func_array([$this->database, $method], $methodArguments);
    }
}
