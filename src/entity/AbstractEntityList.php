<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{EntityException, EntityListInterface, EntityInterface};
use froq\collection\Collection;
use froq\pager\Pager;
use ArrayIterator;

/**
 * Abstract Entity Array.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\AbstractEntityList
 * @author  Kerem Güneş
 * @since   4.2
 */
abstract class AbstractEntityList implements EntityListInterface
{
    /** @var array<froq\database\entity\EntityInterface> */
    private array $items = [];

    /** @var string @since 4.8 */
    private string $itemsClass;

    /** @var ?froq\pager\Pager */
    protected ?Pager $pager;

    /**
     * Constructor.
     *
     * @param array|null            $items
     * @param froq\pager\Pager|null $pager
     * @param array|bool|null       $drop
     * @param bool                  $clean
     * @param array|null            $args Constructor arguments of the target entity.
     */
    public function __construct(array $items = null, Pager $pager = null, array|bool $drop = null, bool $clean = false,
        array $args = null)
    {
        // Create entity class (eg: FooEntityList => FooEntity)
        $this->itemsClass = substr(static::class, 0, -4);

        class_exists($this->itemsClass) || throw new EntityException(
            'Entity class %s not exists, be sure it is defined under the same namespace & directory',
            $this->itemsClass
        );

        // Convert items to related entity.
        if ($items) foreach ($items as $item) {
            if ($item) {
                $this->items[] = new $this->itemsClass($item, $drop, $clean, ...($args ?? []));
            }
        }

        $this->pager = $pager;
    }

    /**
     * Magic - serialize.
     *
     * @return array
     */
    public function __serialize()
    {
        return ['items' => $this->items, 'itemsClass' => $this->itemsClass,
                'pager' => $this->pager];
    }

    /**
     * Magic - unserialize.
     *
     * @param  array $data
     * @return void
     */
    public function __unserialize(array $data)
    {
        ['items' => $this->items, 'itemsClass' => $this->itemsClass,
         'pager' => $this->pager] = $data;
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
     * Get an item from data stack with given index.
     *
     * @param  int $i
     * @return froq\database\entity\EntityInterface|null
     * @since  4.11
     */
    public final function get(int $i): EntityInterface|null
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
     * @return array<froq\database\entity\EntityInterface|null>
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
     * @return froq\database\entity\EntityInterface|null
     */
    public final function first(): EntityInterface|null
    {
        return $this->get(0);
    }

    /**
     * Get last item or return null if no items.
     *
     * @return froq\database\entity\EntityInterface|null
     */
    public final function last(): EntityInterface|null
    {
        return $this->get(count($this->items) - 1);
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
     * Empty entity list dropping all vars.
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
     * Check whether entity list is empty.
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
     * @return static
     * @since  4.8
     */
    public function filter(callable $func, bool $keepKeys = false): static
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
     * @return static
     * @since  4.8
     */
    public function map(callable $func): static
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
     * @return static
     * @since  5.0
     */
    public function each(callable $func): static
    {
        // Stay in here.
        $func = $func->bindTo($this, $this);

        $this->toCollection()->each($func);

        return $this;
    }

    /**
     * Create a collection from entity vars.
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
     * @throws     froq\database\entity\EntityException
     */
    public final function offsetSet($i, $item)
    {
        throw new EntityException('No set() allowed for ' . static::class);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     froq\database\entity\EntityException
     */
    public final function offsetUnset($i)
    {
        throw new EntityException('No unset() allowed for ' . static::class);
    }
}
