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

use froq\database\entity\{EntityException, AbstractEntity};
use froq\pager\Pager;
use Countable, IteratorAggregate, ArrayAccess, ArrayIterator;

/**
 * Abstract Entity Array.
 * @package froq\database\entity
 * @object  froq\database\entity\AbstractEntityArray
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.2
 */
abstract class AbstractEntityArray implements Countable, IteratorAggregate, ArrayAccess
{
    /**
     * Items.
     * @var array
     */
    protected array $items = [];

    /**
     * Pager.
     * @var ?froq\pager\Pager
     */
    protected ?Pager $pager;

    /**
     * Constructor.
     * @param array<froq\database\entity\AbstractEntity>|null $items
     * @param froq\pager\Pager|null                           $pager
     */
    public function __construct(array $items = null, Pager $pager = null)
    {
        if ($items) {
            foreach ($items as $item) {
                if (!$item instanceof AbstractEntity) {
                    throw new EntityException('Each item must be an entity extending "%s" class',
                        [AbstractEntity::class]);
                }

                $this->items[] = $item;
            }
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
    public final function offsetSet($i, $iv)
    {
        throw new EntityException('No set() allowed for "%s"', [static::class]);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     froq\database\entity\EntityException
     */
    public final function offsetUnset($i): void
    {
        throw new EntityException('No unset() allowed for "%s"', [static::class]);
    }
}
