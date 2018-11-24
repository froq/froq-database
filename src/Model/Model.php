<?php
/**
 * Copyright (c) 2015 Kerem Güneş
 *
 * MIT License <https://opensource.org/licenses/mit>
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
     * @var array
     */
    protected $data = [];

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

        // call init if exists
        if (method_exists($this, 'init')) {
            $this->init();
        }
    }

    /** Forbidden magic methods. */
    public final function __set(string $key, $value)
    {
        throw new ModelException("Dynamic set call not allowed, use set() method instead!");
    }
    public final function __get(string $key)
    {
        throw new ModelException("Dynamic get call not allowed, use get() method instead!");
    }
    public final function __isset(string $key)
    {
        throw new ModelException("Dynamic isset call not allowed, use isset() method instead!");
    }
    public final function __unset(string $key)
    {
        throw new ModelException("Dynamic unset call not allowed, use unset() method instead!");
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
            $this->set($this->stackPrimary, $value);
        }
    }

    /**
     * Get stack primary value.
     * @return any
     */
    public final function getStackPrimaryValue()
    {
        if ($this->stackPrimary != null) {
            return $this->get($this->stackPrimary);
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
     * @return array
     */
    public final function getData(): array
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
     * Set.
     * @param  string $key
     * @param  any    $value
     * @return self
     */
    public final function set(string $key, $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Get.
     * @param  string $key
     * @param  any    $valueDefault
     * @return any
     */
    public final function get(string $key, $valueDefault = null)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $valueDefault;
    }

    /**
     * Isset.
     * @param  string $key
     * @return bool
     */
    public final function isset(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Unset.
     * @param  string $key
     * @return void
     */
    public final function unset(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Load.
     * @param  array $data
     * @return self
     */
    public final function load(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Unload.
     * @return self
     */
    public final function unload(): self
    {
        $this->reset();

        return $this;
    }

    /**
     * Reset.
     * @return self
     */
    public final function reset(): self
    {
        $this->data = [];

        return $this;
    }
}
