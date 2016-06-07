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

namespace Froq\Database\Model;

use Froq\Pager\Pager;
use Froq\Database\Vendor\VendorInterface;

/**
 * @package    Froq
 * @subpackage Froq\Database\Model
 * @object     Froq\Database\Model\Model
 * @author     Kerem Güneş <k-gun@mail.com>
 */
abstract class Model implements ModelInterface
{
    /**
     * Db.
     * @var Froq\Database\Vendor\VendorInterface
     */
    protected $db;

    /**
     * Stack.
     * @var string
     */
    protected $stack;

    /**
     * Stack primary.
     * @var string
     */
    protected $stackPrimary;

    /**
     * Use transaction.
     * @var bool
     */
    protected $useTransaction = true;

    /**
     * Pager.
     * @var Froq\Pager\Pager
     */
    protected $pager;

    /**
     * Fail.
     * @var \Throwable|null
     */
    protected $fail;

    /**
     * Data.
     * @var Froq\Database\Model\ModelData
     */
    protected $data;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->pager = new Pager();
        $this->data  = new ModelData();

        // call init if exists
        if (method_exists($this, 'init')) {
            $this->init();
        }
    }

    /**
     * Set.
     * @param string $key
     * @param any    $value
     */
    final public function __set(string $key, $value)
    {
        $this->data->set($key, $value);
    }

    /**
     * Get.
     * @param  string $key
     * @return any
     */
    final public function __get(string $key)
    {
        return $this->data->get($key);
    }

    /**
     * Isset.
     * @param  string $key
     * @return bool
     */
    final public function __isset(string $key): bool
    {
        return $this->data->isset($key);
    }

    /**
     * Unset.
     * @param  string $key
     * @return void
     */
    final public function __unset(string $key)
    {
        return $this->data->unset($key);
    }

    /**
     * Set db.
     * @param Froq\Database\Vendor\VendorInterface $db
     */
    final public function setDb(VendorInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Get db.
     * @return Froq\Database\Vendor\VendorInterface
     */
    final public function getDb(): VendorInterface
    {
        return $this->db;
    }

    /**
     * Set pager.
     * @param Froq\Pager\Pager $pager
     */
    final public function setPager(Pager $pager)
    {
        $this->pager = $pager;
    }

    /**
     * Get pager.
     * @return Froq\Pager\Pager|null
     */
    final public function getPager()
    {
        return $this->pager;
    }

    /**
     * Set fail.
     * @param  \Throwable $fail
     * @return void
     */
    final public function setFail(\Throwable $fail)
    {
        $this->fail = $fail;
    }

    /**
     * Get fail.
     * @return \Throwable|null
     */
    final public function getFail()
    {
        return $this->fail;
    }

    /**
     * Is fail.
     * @return bool
     */
    final public function isFail(): bool
    {
        return ($this->fail != null);
    }

    /**
     * Set stack primary value.
     * @param  any $value
     * @return void
     */
    final public function setStackPrimaryValue($value)
    {
        $this->data->set($this->stackPrimary, $value);
    }

    /**
     * Get stack primary value.
     * @return any
     */
    final public function getStackPrimaryValue()
    {
        return $this->data->get($this->stackPrimary);
    }

    /**
     * Load.
     * @param  array $data
     * @return void
     */
    final public function load(array $data)
    {
        foreach ($data as $key => $value) {
            $this->data->set($key, $value);
        }
    }

    /**
     * Unload.
     * @return void
     */
    final public function unload()
    {
        foreach ($this->data->keys() as $key) {
            $this->data->unset($key);
        }
    }
}
