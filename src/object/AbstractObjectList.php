<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\object;

use froq\database\object\{ObjectListException, ObjectListInterface, ObjectInterface};
use froq\collection\Collection;
use froq\pager\Pager;
use ArrayIterator;

/**
 * Object List.
 *
 * Represents a list-like collection entity that used for data stacking the subclasses derived from
 * AbstractObject (ObjectInterface) class, and also has many utility methods all ready-to-use.
 *
 * Although it has no abstract method, it is abstracted due to it is not useable as standalone.
 *
 * @package froq\database\object
 * @object  froq\database\object\AbstractObjectList
 * @author  Kerem Güneş
 * @since   4.2, 5.0 Moved in "object" subpackage.
 */
abstract class AbstractObjectList implements ObjectListInterface
{
    /** @var array<froq\database\object\ObjectInterface> */
    private array $items = [];

    /** @var string @since 4.8 */
    private string $itemsClass;

    /** @var froq\pager\Pager|null */
    protected Pager|null $pager = null;

    /**
     * Constructor.
     *
     * @param array|null            $items
     * @param froq\pager\Pager|null $pager
     * @param string|null           $itemsClass     The target object.
     * @param ...                   $itemsClassArgs The target object constructor arguments.
     */
    public function __construct(array $items = null, Pager $pager = null, string $itemsClass = null,
        ...$itemsClassArgs)
    {
        // Set object class with given or from name (eg: FooObjectList => FooObject)
        $this->itemsClass = $itemsClass ?? substr(static::class, 0, -4);

        class_exists($this->itemsClass) || throw new ObjectListException(
            'Object class `%s` not exists, be sure it is defined under the same namespace & directory '.
            'or pass $itemsClass argument to constructor', [$this->itemsClass]
        );

        // Convert items to related object.
        if ($items) foreach ($items as $item) {
            // Item may be an array or object.
            if (!$item instanceof ObjectInterface) {
                $item = new $this->itemsClass($item, ...$itemsClassArgs);
            }

            $this->add($item);
        }

        $this->pager = $pager;
    }

    /**
     * Magic - serialize.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return ['items' => $this->items, 'itemsClass' => $this->itemsClass, 'pager' => $this->pager];
    }

    /**
     * Magic - unserialize.
     *
     * @param  array $data
     * @return void
     */
    public function __unserialize(array $data)
    {
        ['items' => $this->items, 'itemsClass' => $this->itemsClass, 'pager' => $this->pager] = $data;
    }

    /**
     * Check whether an item set on data stack with given index.
     *
     * @param  int $i
     * @return bool
     */
    public final function has(int $i): bool
    {
        return isset($this->items[$i]);
    }

    /**
     * Add an item into data stack with next index.
     *
     * @param  froq\database\object\ObjectInterface $item
     * @since  5.0
     * @return self
     */
    public final function add(ObjectInterface $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Set an item onto data stack with given index.
     *
     * @param  int $i
     * @param  froq\database\object\ObjectInterface|null $item
     * @since  5.0
     * @return self
     */
    public final function set(int $i, ObjectInterface|null $item): self
    {
        $this->items[$i] = $item;

        return $this;
    }

    /**
     * Get an item from data stack with given index.
     *
     * @param  int $i
     * @return froq\database\object\ObjectInterface|null
     * @since  4.11
     */
    public final function get(int $i): ObjectInterface|null
    {
        return $this->items[$i] ?? null;
    }

    /**
     * Get an item.
     *
     * @alias of get()
     */
    public final function item(int $i)
    {
        return $this->get($i);
    }

    /**
     * Get all items.
     *
     * @return array<froq\database\object\ObjectInterface|null>
     */
    public final function items(): array
    {
        return $this->items;
    }

    /**
     * Get items class.
     *
     * @return string
     * @since  4.8
     */
    public final function itemsClass(): string
    {
        return $this->itemsClass;
    }

    /**
     * Get first item or return null if no items.
     *
     * @return froq\database\object\ObjectInterface|null
     */
    public final function first(): ObjectInterface|null
    {
        return $this->get(0);
    }

    /**
     * Get last item or return null if no items.
     *
     * @return froq\database\object\ObjectInterface|null
     */
    public final function last(): ObjectInterface|null
    {
        return $this->get($this->count() - 1);
    }

    /**
     * Get pager property.
     *
     * @return froq\pager\Pager|null
     */
    public final function pager(): Pager|null
    {
        return $this->pager;
    }

    /**
     * Empty object list dropping all vars.
     *
     * @return self
     * @since  5.0
     */
    public function empty(): self
    {
        foreach (array_keys($this->items) as $i) {
            unset($this->items[$i]);
        }

        return $this;
    }

    /**
     * Check whether object list is empty.
     *
     * @return bool
     * @since  5.0
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Filter.
     *
     * @param  callable $func
     * @param  bool     $keepKeys
     * @return self
     * @since  4.8
     */
    public function filter(callable $func, bool $keepKeys = false): self
    {
        // Stay in here.
        $func = $func->bindTo($this, $this);

        $this->items = $this->toCollection()->filter($func, $keepKeys)->toArray();

        return $this;
    }

    /**
     * Map.
     *
     * @param  callable $func
     * @return self
     * @since  4.8
     */
    public function map(callable $func): self
    {
        // Stay in here.
        $func = $func->bindTo($this, $this);

        $this->items = $this->toCollection()->map($func)->toArray();

        return $this;
    }

    /**
     * Reduce.
     *
     * @param  any      $carry
     * @param  callable $func
     * @return any
     * @since  4.8
     */
    public function reduce($carry, callable $func)
    {
        // Stay in here.
        $func = $func->bindTo($this, $this);

        return $this->toCollection()->reduce($carry, $func);
    }

    /**
     * Aggregate.
     *
     * @param  callable   $func
     * @param  array|null $carry
     * @return array
     * @since  4.10
     */
    public function aggregate(callable $func, array $carry = null): array
    {
        // Stay in here.
        $func = $func->bindTo($this, $this);

        return $this->toCollection()->aggregate($func, $carry);
    }

    /**
     * Each.
     *
     * @param  callable $func
     * @return self
     * @since  5.0
     */
    public function each(callable $func): self
    {
        // Stay in here.
        $func = $func->bindTo($this, $this);

        $this->toCollection()->each($func);

        return $this;
    }

    /**
     * Create a collection from object vars.
     *
     * @return froq\collection\Collection
     * @since  4.8
     */
    public function toCollection(): Collection
    {
        return new Collection($this->items);
    }

    /**
     * @inheritDoc froq\common\interface\Arrayable
     * @since      4.5
     */
    public function toArray(bool $deep = false): array
    {
        $ret = [];

        foreach ($this->items as $item) {
            $ret[] = $item->toArray($deep);
        }

        return $ret;
    }

    /**
     * @inheritDoc Countable
     */
    public final function count(): int
    {
        return count($this->items);
    }

    /**
     * @inheritDoc IteratorAggregate
     */
    public final function getIterator(): iterable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @inheritDoc JsonSerializable
     * @since      4.11
     */
    public final function jsonSerialize(): array
    {
        return $this->toArray(true);
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public final function offsetExists($i)
    {
        return $this->has($i);
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public final function offsetGet($i)
    {
        return $this->get($i);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     froq\database\object\ObjectListException
     */
    public final function offsetSet($i, $item)
    {
        throw new ObjectListException('No set() allowed for ' . static::class);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     froq\database\object\ObjectListException
     */
    public final function offsetUnset($i)
    {
        throw new ObjectListException('No unset() allowed for ' . static::class);
    }
}
