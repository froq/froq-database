<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{EntityException, EntityArrayInterface, AbstractEntity};
use froq\collection\Collection;
use froq\pager\Pager;
use ArrayIterator;

/**
 * Abstract Entity Array.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\AbstractEntityArray
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.2
 */
abstract class AbstractEntityArray implements EntityArrayInterface
{
    /**
     * Items.
     * @var array<froq\database\entity\AbstractEntity>
     */
    private array $items = [];

    /**
     * Items class.
     * @var string
     * @since 4.8
     */
    private string $itemsClass;

    /**
     * Pager.
     * @var ?froq\pager\Pager
     */
    protected ?Pager $pager;

    /**
     * Constructor.
     * @param array|null            $items
     * @param froq\pager\Pager|null $pager
     * @param array|bool|null       $drop
     */
    public function __construct(array $items = null, Pager $pager = null, $drop = null)
    {
        // Create entity class (eg: FooEntityArray => FooEntity)
        $this->itemsClass = substr(static::class, 0, -5);

        if (!class_exists($this->itemsClass)) {
            throw new EntityException("Entity class '%s' not exists, be sure it is defined under the ".
                "same namespace & directory", $this->itemsClass);
        }

        // Convert items to related entity.
        if ($items) foreach ($items as $item) {
            $this->items[] = new $this->itemsClass($item, $drop);
        }

        $this->pager = $pager;
    }

    /**
     * Serialize.
     * @return array
     */
    public function __serialize()
    {
        return ['items' => $this->items, 'itemsClass' => $this->itemsClass,
                'pager' => $this->pager];
    }

    /**
     * Unserialize.
     * @param  array $data
     * @return void
     */
    public function __unserialize($data)
    {
        ['items' => $this->items, 'itemsClass' => $this->itemsClass,
         'pager' => $this->pager] = $data;
    }

    /**
     * Has.
     * @param  int $i
     * @return bool
     */
    public final function has(int $i): bool
    {
        return isset($this->items[$i]);
    }

    /**
     * Get.
     * @param  int $i
     * @return ?froq\database\entity\AbstractEntity
     * @since  4.11
     */
    public final function get(int $i): ?AbstractEntity
    {
        return $this->items[$i] ?? null;
    }

    /**
     * Item.
     * @alias of get()
     */
    public final function item(int $i): ?AbstractEntity
    {
        return $this->get($i);
    }

    /**
     * Items.
     * @return array<froq\database\entity\AbstractEntity|null>
     */
    public final function items(): array
    {
        return $this->items;
    }

    /**
     * Items class.
     * @return string
     * @since  4.8
     */
    public final function itemsClass(): string
    {
        return $this->itemsClass;
    }

    /**
     * First.
     * @return ?froq\database\entity\AbstractEntity
     */
    public final function first(): ?AbstractEntity
    {
        return $this->item(0);
    }

    /**
     * Last.
     * @return ?froq\database\entity\AbstractEntity
     */
    public final function last(): ?AbstractEntity
    {
        return $this->item(count($this->items) - 1);
    }

    /**
     * Pager.
     * @return ?froq\pager\Pager
     */
    public final function pager(): ?Pager
    {
        return $this->pager;
    }

    /**
     * Filter.
     * @param  callable $func
     * @param  bool     $keepKeys
     * @return self (static)
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
     * @param  callable $func
     * @return self (static)
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
     * To collection.
     * @return froq\collection\Collection
     * @since  4.8
     */
    public function toCollection(): Collection
    {
        return new Collection($this->items);
    }

    /**
     * @inheritDoc froq\common\interfaces\Arrayable
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
        throw new EntityException("No set() allowed for '%s' object", [static::class]);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     froq\database\entity\EntityException
     */
    public final function offsetUnset($i)
    {
        throw new EntityException("No unset() allowed for '%s' object", [static::class]);
    }
}
