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

namespace froq\database\model;

use froq\service\Service;
use froq\database\DatabaseException;
use froq\database\vendor\VendorInterface;
use froq\pager\Pager;

/**
 * Model.
 * @package froq\database\model
 * @object  froq\database\model\Model
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   1.0
 */
abstract class Model
{
    /**
     * Service.
     * @var froq\service\Service
     */
    protected $service;

    /**
     * Vendor.
     * @var froq\database\vendor\VendorInterface
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
     * @var froq\pager\Pager
     */
    private $pager;

    /**
     * Data.
     * @var array
     */
    private $data = [];

    /**
     * Fail.
     * @var \Throwable
     */
    private $fail;

    /**
     * Fail option.
     * @var bool
     */
    private $failOption;

    /**
     * Fail log directory.
     * @var string
     */
    private $failLogDirectory = APP_DIR .'/tmp/log/db';

    /**
     * Constructor.
     * @param  froq\service\Service $service
     * @throws froq\database\DatabaseException
     */
    public function __construct(Service $service)
    {
        // all must be set in child class: $vendorName, $stack, $stackPrimary
        if ($this->vendorName == null) {
            throw new DatabaseException(sprintf("Null \$vendorName, set in '%s' class",
                static::class));
        }

        $this->vendor = $service->getApp()->getDatabase()->init($this->vendorName);
        $this->service = $service;
        $this->pager = new Pager();

        // escape identifiers
        if ($this->stack != null || $this->stackPrimary != null) {
            $agent = $this->vendor->getDatabase()->getLink()->getAgent();
            $this->stack && $this->stack = $agent->escapeIdentifier($this->stack);
            $this->stackPrimary && $this->stackPrimary = $agent->escapeIdentifier($this->stackPrimary);
        }

        // call init method if exists
        if (method_exists($this, 'init')) {
            $this->init();
        }
    }

    /** Forbidden magic methods. */
    public final function __set(string $key, $value)
    {
        throw new DatabaseException('Magic __set() not allowed, use set() method instead');
    }
    public final function __get(string $key)
    {
        throw new DatabaseException('Magic __get() not allowed, use get() method instead');
    }
    public final function __isset(string $key)
    {
        throw new DatabaseException('Magic __isset() not allowed, use isset() method instead');
    }
    public final function __unset(string $key)
    {
        throw new DatabaseException('Magic __unset() not allowed, use unset() method instead');
    }

    /**
     * Get service.
     * @return froq\service\Service
     */
    public final function getService(): Service
    {
        return $this->service;
    }

    /**
     * Get vendor.
     * @return froq\database\vendor\VendorInterface
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
     * Get vendor agent.
     * @return object
     */
    public final function getVendorAgent(): object
    {
        return $this->vendor->getDatabase()->getLink()->getAgent();
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
     * @param  int|null    $totalRecords
     * @param  string|null $startKey
     * @param  string|null $stopKey
     * @return froq\pager\Pager
     */
    public final function getPager(int $totalRecords = null, string $startKey = null,
        string $stopKey = null): Pager
    {
        if ($totalRecords !== null) {
            $this->pager->run($totalRecords, $startKey, $stopKey);
        }

        return $this->pager;
    }

    /**
     * Get pager results.
     * @param  int         $totalRecords
     * @param  string|null $startKey
     * @param  string|null $stopKey
     * @return array
     */
    public final function getPagerResults(int $totalRecords, string $startKey = null,
        string $stopKey = null): array
    {
        return $this->pager->run($totalRecords, $startKey, $stopKey);
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
     * Is fail.
     * @return bool
     */
    public final function isFail(): bool
    {
        return $this->fail != null;
    }

    /**
     * Set fail.
     * @param  \Throwable $fail
     * @return void
     * @throws \Throwable
     */
    public final function setFail(\Throwable $fail): void
    {
        if ($this->failOption) {
            if ($this->failOption == 'log') {
                $logger = clone $this->service->getApp()->getLogger();
                $logger->setDirectory($this->failLogDirectory);
                $logger->logFail($fail);
            } elseif ($this->failOption == 'throw') {
                throw $fail;
            }
        }
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
     * Set fail option.
     * @param  string $failOption
     * @return vaoid
     */
    public final function setFailOption(string $failOption): void
    {
        $this->failOption = $failOption;
    }

    /**
     * Get fail option.
     * @return ?string
     */
    public final function getFailOption(): ?string
    {
        return $this->failOption;
    }

    /**
     * Get fail log directory.
     * @return string
     */
    public final function getFailLogDirectory(): string
    {
        return $this->failLogDirectory;
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
