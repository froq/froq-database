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

use froq\database\entity\{EntityException, EntityInterface};
use ArrayIterator, Traversable;

/**
 * Abstract Entity.
 * @package froq\database\entity
 * @object  froq\database\entity\AbstractEntity
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.2
 */
abstract class AbstractEntity implements EntityInterface
{
    /**
     * Drop.
     * @var array|bool|null
     */
    private $__drop;

    /**
     * Constructor.
     * @param array|null      $data
     * @param array|bool|null $drop
     */
    public function __construct(array $data = null, $drop = null)
    {
        $data = $data ?? [];

        if ($data) {
            foreach ($data as $name => $value) {
                $this->{$name} = $value;
            }
        }

        if ($drop) {
            $vars = $this->getVarNames(); $varsDiff = [];
            if ($drop === true) {
                $varsDiff = (count($vars) > count($data))
                    ? array_diff($vars, array_keys($data))
                    : array_diff(array_keys($data), $vars);
            } elseif (is_array($drop)) {
                $varsDiff = $drop;
            }

            foreach ($varsDiff as $var) {
                if (!isset($this->{$var}) || !!$drop) {
                     unset($this->{$var});
                }
            }

            // Add tick.
            $this->__drop = $drop;
        }
    }

    /**
     * Serialize.
     * @return array
     */
    public function __serialize()
    {
        return $this->getVarValues(false);
    }

    /**
     * Unserialize.
     * @param  array $data
     * @return void
     */
    public function __unserialize($data)
    {
        // Needed set all first, cos PHP creates new entity object with all vars.
        foreach ($data as $name => $value) {
            $this->{$name} = $value;
        }

        // Then check drop.
        if ($this->__drop) {
            $vars = is_array($this->__drop) ? $this->__drop : $this->getVarNames();
            foreach ($vars as $var) {
                if (!isset($this->{$var})) {
                     unset($this->{$var});
                }
            }
        }

        // Then remove "drop" var.
        unset($this->__drop);
    }

    /**
     * Set.
     * @param  string $name
     * @param  any    $value
     * @return void
     */
    public function __set($name, $value)
    {
        // Note: used for undefined (or/and dropped) vars.
        $this->{$name} = $value;
    }

    /**
     * Get.
     * @param  string $name
     * @return any
     */
    public function __get($name)
    {
        // Note: used for undefined (or/and dropped) vars.
        return $this->{$name} ?? null;
    }

    /**
     * Call.
     * @param  string $call
     * @param  array  $callArgs
     * @return any
     * @throws froq\database\entity\EntityException
     */
    public function __call($call, $callArgs)
    {
        // Eg: id().
        if (property_exists($this, $call)) {
            return $callArgs
                ? $this->__set($call, $callArgs[0])
                : $this->__get($call);
        }

        $cmd = substr($call, 0, 3);

        // Eg: setId(), getId().
        switch ($cmd) {
            case 'set':
                $name = lcfirst(substr($call, 3));
                if (property_exists($this, $name)) {
                    return $this->__set($name, $callArgs[0]);
                }
                break;
            case 'get';
                $name = lcfirst(substr($call, 3));
                if (property_exists($this, $name)) {
                    return $this->__get($name);
                }
                break;
        }

        throw new EntityException('Bad method call as "%s()" on "%s" object', [$call, static::class]);
    }

    /**
     * Has.
     * @param  int $var
     * @return bool
     */
    public final function has(int $var): bool
    {
        return isset($this->{$var});
    }

    /**
     * Has var.
     * @param  int $var
     * @return bool
     */
    public final function hasVar(int $var): bool
    {
        return property_exists($this, $var);
    }

    /**
     * Get var names.
     * @return array
     */
    public final function getVarNames(): array
    {
        // Note: returns non-defined vars also.
        $ret = get_class_vars(static::class);
        unset($ret['__drop']);

        return array_keys($ret);
    }

    /**
     * Get var values.
     * @param  bool $privates
     * @return array
     */
    public final function getVarValues(bool $privates = true): array
    {
        // Note: returns defined vars only.
        $ret = get_object_vars($this);
        unset($ret['__drop']);

        if (!$privates) {
            $ret = array_filter($ret, fn($v) => ($v[0] !== '_'), 2);
        }

        return $ret;
    }

    /**
     * Id (shortcut for IDs).
     * @return int|string|null
     */
    public function id()
    {
        return $this->id ?? null;
    }

    /**
     * @inheritDoc froq\common\interfaces\Arrayable
     * @since 4.5
     */
    public function toArray(bool $deep = false): array
    {
        $ret = $this->getVarValues();

        if ($deep) {
            // Memoize array maker.
            static $toArray; $toArray ??= function ($in, $deep) use (&$toArray) {
                if ($in && is_object($in)) {
                    $out = (array) (
                        ($in instanceof Traversable) ? iterator_to_array($in) : (
                            method_exists($in, 'toArray') ? $in->toArray() : (
                                get_object_vars($in)
                            )
                        )
                    );
                } else {
                    $out = (array) $in;
                }

                if ($deep) {
                    // Overwrite.
                    foreach ($out as $key => $value) {
                        if ($value instanceof EntityInterface) {
                            $out[$key] = $value->toArray(true);
                            continue;
                        }

                        $out[$key] = is_iterable($value) || is_object($value)
                            ? $toArray($value, true) : $value;
                    }
                }

                return $out;
            };

            $ret = $toArray($ret, $deep);
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
        return count($this->getVarValues());
    }

    /**
     * @inheritDoc IteratorAggregate
     */
    public final function getIterator(): iterable
    {
        // Note: this method goes to toArray() for iterable check.
        return new ArrayIterator($this->getVarValues());
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public final function offsetExists($var)
    {
        return ($this->{$var} ?? null) !== null;
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public final function offsetGet($var)
    {
        return ($this->{$var} ?? null);
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
