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

namespace froq\database\entity;

use froq\database\entity\{EntityException, EntityInterface, AbstractEntity};
use froq\pager\Pager;
use ArrayIterator;

/**
 * Abstract Entity Array.
 * @package froq\database\entity
 * @object  froq\database\entity\AbstractEntityArray
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.2
 */
abstract class AbstractEntityArray implements EntityInterface
{
    /**
     * Items.
     * @var array<froq\database\entity\AbstractEntity>
     */
    protected array $items = [];

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
        // Check entity class (eg: FooEntityArray => FooEntity)
        $class = substr(static::class, 0, -5);
        if (!class_exists($class)) {
            throw new EntityException('Entity class "%s" not exists, be sure it is defined in the '.
                'same namespace & directory', [$class]);
        }

        // Convert items to related entity.
        if ($items) foreach ($items as $item) {
            $this->items[] = new $class($item, $drop);
        }

        $this->pager = $pager;
    }

    /**
     * Serialize.
     * @return array
     */
    public function __serialize()
    {
        return ['items' => $this->items, 'pager' => $this->pager];
    }

    /**
     * Unserialize.
     * @param  array $data
     * @return void
     */
    public function __unserialize($data)
    {
        $this->items = $data['items']; $this->pager = $data['pager'];
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
     * Items
     * @param  int $i
     * @return ?froq\database\entity\AbstractEntity
     */
    public final function item(int $i): ?AbstractEntity
    {
        return $this->items[$i] ?? null;
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
     * @inheritDoc froq\common\interfaces\Arrayable
     * @since 4.5
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
     * @inheritDoc froq\common\interfaces\Jsonable
     * @since 4.5
     */
    public function toJson(int $flags = 0, bool $deep = false): string
    {
        return json_encode($this->toArray($deep), $flags);
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
        return $this->item($i);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     froq\database\entity\EntityException
     */
    public final function offsetSet($var, $varval)
    {
        throw new EntityException('No set() allowed for "%s"', [static::class]);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     froq\database\entity\EntityException
     */
    public final function offsetUnset($var)
    {
        throw new EntityException('No unset() allowed for "%s"', [static::class]);
    }
}
