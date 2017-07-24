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

use Froq\Service\ServiceInterface;
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
     * Vendor.
     * @var Froq\Database\Vendor\VendorInterface
     */
    protected $vendor;

    /**
     * Vendor name.
     * @var string
     */
    protected $vendorName;

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
     * Data.
     * @var Froq\Database\Model\ModelData
     */
    protected $data;

    /**
     * Fail.
     * @var \Throwable|null
     */
    protected $fail;

    /**
     * Constructor.
     * @param Froq\Service\ServiceInterface
     */
    public function __construct(ServiceInterface $service)
    {
        if (!$this->vendorName) {
            throw new ModelException(sprintf('$vendorName not set in %s model class!', get_called_class()));
        }
        if (!$this->stack || !$this->stackPrimary) {
            throw new ModelException(sprintf('Both $stack and $stackPrimary must be set in %s first!', get_called_class()));
        }

        $this->vendor = $service->app->database->init($this->vendorName);

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
     * Set database.
     * @param  Froq\Database\Vendor\VendorInterface $database
     * @return void
     */
    final public function setVendor(VendorInterface $vendor)
    {
        $this->vendor = $vendor;
    }

    /**
     * Get vendor.
     * @return Froq\Database\Vendor\VendorInterface
     */
    final public function getVendor(): VendorInterface
    {
        return $this->vendor;
    }

    /**
     * Set stack.
     * @param  string $stack
     * @return void
     */
    final public function setStack(string $stack)
    {
        $this->stack = $stack;
    }

    /**
     * Get stack.
     * @return string|null
     */
    final public function getStack()
    {
        return $this->stack;
    }

    /**
     * Set stack primary.
     * @param  string $stackPrimary
     * @return void
     */
    final public function setStackPrimary(string $stackPrimary)
    {
        $this->stackPrimary = $stackPrimary;
    }

    /**
     * Get stack primary.
     * @return string|null
     */
    final public function getStackPrimary()
    {
        return $this->stackPrimary;
    }

    /**
     * Set stack primary value.
     * @param  any $value
     * @return void
     */
    final public function setStackPrimaryValue($value)
    {
        if ($this->stackPrimary) {
            $this->data->set($this->stackPrimary, $value);
        }
    }

    /**
     * Get stack primary value.
     * @return any
     */
    final public function getStackPrimaryValue()
    {
        if ($this->stackPrimary) {
            return $this->data->get($this->stackPrimary);
        }
    }

    /**
     * Set pager.
     * @param  Froq\Pager\Pager $pager
     * @return void
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
     * Uses transaction.
     * @return bool
     */
    final public function usesTransaction(): bool
    {
        return !!$this->useTransaction;
    }

    /**
     * Load.
     * @param  array $data
     * @return self
     */
    final public function load(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->data->set($key, $value);
        }

        return $this;
    }

    /**
     * Unload.
     * @return self
     */
    final public function unload(): self
    {
        foreach ($this->data->keys() as $key) {
            $this->data->unset($key);
        }

        return $this;
    }

    /**
     * Reset.
     * @return void
     */
    final public function reset()
    {
        $this->data->empty();
    }
}
