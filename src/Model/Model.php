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
 * @subpackage Froq\Database
 * @object     Froq\Database\Model\Model
 * @author     Kerem Güneş <k-gun@mail.com>
 */
abstract class Model implements ModelInterface
{
    /**
     * Service.
     * @var Froq\Service\Service
     */
    protected $service;

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
     */
    public function __construct()
    {
        // all must be set in child class: $vendorName, $stack, $stackPrimary
        if ($this->vendorName == null) {
            throw new ModelException(sprintf('$vendorName not set in %s model class!', get_called_class()));
        }

        // cannot rid of init'ing like new FooModel() without $service argument
        $app = app();
        if ($app == null) {
            throw new ModelException('No $app found in global scope!');
        }

        $this->service = $app->service();
        $this->vendor = $this->service->getApp()->db()->init($this->vendorName);

        $this->pager = new Pager();
        $this->data = new ModelData();

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
    public final function __set(string $key, $value)
    {
        $this->data->set($key, $value);
    }

    /**
     * Get.
     * @param  string $key
     * @return any
     */
    public final function __get(string $key)
    {
        return $this->data->get($key);
    }

    /**
     * Isset.
     * @param  string $key
     * @return bool
     */
    public final function __isset(string $key)
    {
        return $this->data->isset($key);
    }

    /**
     * Unset.
     * @param  string $key
     * @return void
     */
    public final function __unset(string $key)
    {
        return $this->data->unset($key);
    }

    /**
     * Get service.
     * @return Froq\Service\Service
     */
    public final function getService(): Service
    {
        return $this->service;
    }

    /**
     * Get vendor.
     * @return Froq\Database\Vendor\VendorInterface
     */
    public final function getVendor(): VendorInterface
    {
        return $this->vendor;
    }

    /**
     * Get vendor name.
     * @return string
     */
    public final function getVendorName(): string
    {
        return $this->vendorName;
    }

    /**
     * Get stack.
     * @return ?string
     */
    public final function getStack(): ?string
    {
        return $this->stack;
    }

    /**
     * Get stack primary.
     * @return ?string
     */
    public final function getStackPrimary(): ?string
    {
        return $this->stackPrimary;
    }

    /**
     * Set stack primary value.
     * @param  any $value
     * @return void
     */
    public final function setStackPrimaryValue($value): void
    {
        if ($this->stackPrimary != null) {
            $this->data->set($this->stackPrimary, $value);
        }
    }

    /**
     * Get stack primary value.
     * @return any
     */
    public final function getStackPrimaryValue()
    {
        if ($this->stackPrimary != null) {
            return $this->data->get($this->stackPrimary);
        }
    }

    /**
     * Get pager.
     * @return Froq\Pager\Pager
     */
    public final function getPager(): Pager
    {
        return $this->pager;
    }

    /**
     * Get data.
     * @return Froq\Database\Model\ModelData
     */
    public final function getData(): ModelData
    {
        return $this->data;
    }

    /**
     * Set fail.
     * @param  \Throwable $fail
     * @return void
     */
    public final function setFail(\Throwable $fail): void
    {
        $this->fail = $fail;
    }

    /**
     * Get fail.
     * @return \Throwable
     */
    public final function getFail(): ?\Throwable
    {
        return $this->fail;
    }

    /**
     * Is fail.
     * @return bool
     */
    public final function isFail(): bool
    {
        return $this->fail != null;
    }

    /**
     * Uses transaction.
     * @return bool
     */
    public final function usesTransaction(): bool
    {
        return $this->useTransaction == true;
    }

    /**
     * Load.
     * @param  array $data
     * @return self
     */
    public final function load(array $data): self
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
    public final function unload(): self
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
    public final function reset(): void
    {
        $this->data->empty();
    }
}
