<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{EntityException, EntityInterface};
use froq\collection\Collection;
use ArrayIterator, Traversable, Error;

/**
 * Abstract Entity.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\AbstractEntity
 * @author  Kerem Güneş
 * @since   4.2
 */
abstract class AbstractEntity implements EntityInterface
{
    /**
     * Constructor.
     *
     * @param array|null      $data
     * @param array|bool|null $drop
     */
    public function __construct(array $data = null, array|bool $drop = null)
    {
        $data = $data ?? [];

        foreach ($data as $var => $value) {
            $this->{$var} = $value;
        }

        // Drop unused/unwanted vars.
        if ($drop) {
            $vars = $this->getVarNames();
            $diff = [];

            // Unused vars (all nulls).
            if ($drop === true) {
                $diff = count($vars) > count($data)
                          ? array_diff($vars, array_keys($data))
                          : array_diff(array_keys($data), $vars);
            }
            // Unwanted vars.
            else {
                $diff = $drop;
            }

            // Clear unused/unwanted vars.
            foreach ($diff as $var) {
                if (!isset($this->{$var}) /* eg: id=null */ or
                    !!$drop               /* eg: drop=['id'] */) {
                    try {
                        unset($this->{$var});
                    } catch (Error) {}
                }
            }
        }
    }

    /**
     * Magic - serialize.
     *
     * @return array
     */
    public function __serialize()
    {
        return $this->getVars();
    }

    /**
     * Magic - unserialize.
     *
     * @param  array $data
     * @return void
     */
    public function __unserialize(array $data)
    {
        // First: set all vars (cos PHP creates new entity object with all vars).
        foreach ($data as $var => $value) {
            $this->{$var} = $value;
        }

        // Then drop unused vars.
        $diff = array_diff($this->getVarNames(), array_keys($data));
        foreach ($diff as $var) {
            unset($this->{$var});
        }
    }

    /**
     * Magic - set.
     *
     * @param  string $var
     * @param  any    $value
     * @return self
     */
    public function __set(string $var, $value)
    {
        // Prevent "access private property".
        try {
            $this->{$var} = $value;
        } catch (Error) {}

        return $this;
    }

    /**
     * Magic - get.
     *
     * @param  string $var
     * @return any|null
     */
    public function __get(string $var)
    {
        // Prevent "access private property".
        try {
            return $this->{$var} ?? null;
        } catch (Error) {}
    }

    /**
     * Magic - call: provides ability such calls `setId()`, `getId()` etc. on extender classes, throws an
     * `EntityException` if an invalid call comes.
     *
     * @param  string $call
     * @param  array  $callArgs
     * @return any
     * @throws froq\database\entity\EntityException
     * @todo   Drop?
     */
    public function __call(string $call, array $callArgs)
    {
        // Eg: id().
        if (property_exists($this, $call)) {
            return $callArgs ? $this->__set($call, $callArgs[0])
                             : $this->__get($call);
        }

        $var = lcfirst(substr($call, 3));

        // Eg: setId(), getId().
        if (str_starts_with($call, 'set')) {
            return property_exists($this, $var) && $callArgs ? $this->__set($var, $callArgs[0])
                : throw new EntityException('No call argument given for %s() call on entity %s',
                    [$call, static::class]);
        } elseif (str_ends_with($call, 'get')) {
            return property_exists($this, $var) ? $this->__get($var) : null;
        }

        throw new EntityException('Invalid call as %s() on entity %s', [$call, static::class]);
    }

    /**
     * Check whether a var set on entity.
     *
     * @param  string $var
     * @return bool
     */
    public final function has(string $var): bool
    {
        return isset($this->{$var});
    }

    /**
     * Check whether a var defined on entity.
     *
     * @param  string $var
     * @return bool
     * @since  4.11
     */
    public final function hasVar(string $var): bool
    {
        return property_exists($this, $var);
    }

    /**
     * Get a var value if it set.
     *
     * @param  string $var
     * @return any|null
     * @since  4.11
     */
    public final function get(string $var)
    {
        return isset($this->{$var}) ? $this->{$var} : null;
    }

    /**
     * Get vars.
     *
     * @param  bool $all
     * @return array
     * @since  4.11 Replaced with getVarValues().
     */
    public final function getVars(bool $all = true): array
    {
        // Note: returns defined vars only.
        $vars = get_object_vars($this);

        // Filter private/protected vars.
        if (!$all) {
            $vars = array_filter($vars, fn($v) => $v[0] != '_', 2);
        }

        return $vars;
    }

    /**
     * Get var names.
     *
     * @param  bool $all
     * @return array
     */
    public final function getVarNames(bool $all = true): array
    {
        // Note: returns non-defined vars also.
        return array_keys($this->getVars($all));
    }

    /**
     * Get var values.
     *
     * @param  bool $all
     * @return array
     */
    public final function getVarValues(bool $all = true): array
    {
        // Note: returns defined vars only.
        return array_values($this->getVars($all));
    }

    /**
     * Id, shortcut for IDs.
     *
     * @return int|string|null
     */
    public function id(): int|string|null
    {
        return $this->get('id');
    }

    /**
     * Check whether entity is empty.
     *
     * @return bool
     * @since  4.11
     */
    public function isEmpty(): bool
    {
        return empty($this->getVars());
    }

    /**
     * Filter.
     *
     * @return static
     * @since  4.12
     */
    public function filter(callable $func = null): static
    {
        $filtered = array_filter(
            $vars = $this->getVars(),
            $func ?? fn($v) => $v !== null, // Filter nulls only.
        );

        foreach (array_keys($vars) as $var) {
            try {
                if (!isset($filtered[$var])) {
                    unset($this->{$var});
                }
            } catch (Error) {}
        }

        return $this;
    }

    /**
     * Create a collection from entity vars.
     *
     * @param  bool $deep
     * @return froq\collection\Collection
     * @since  4.8
     */
    public function toCollection(bool $deep = false): Collection
    {
        return new Collection($this->toArray($deep));
    }

    /**
     * @inheritDoc froq\common\interface\Arrayable
     * @since      4.5
     */
    public function toArray(bool $deep = false): array
    {
        return !$deep ? $this->getVars()
                      : self::toArrayDeep($this->getVars());
    }

    /**
     * @inheritDoc Countable
     */
    public final function count(): int
    {
        return count($this->getVars());
    }

    /**
     * @inheritDoc IteratorAggregate
     */
    public final function getIterator(bool $deep = false): iterable
    {
        // Note: this method goes to toArray() for iterable check.
        return new ArrayIterator($this->toArray($deep));
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
    public final function offsetExists($var)
    {
        return $this->has($var);
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public final function offsetGet($var)
    {
        return $this->get($var);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     froq\database\entity\EntityException
     */
    public final function offsetSet($var, $value)
    {
        throw new EntityException('No set() allowed for ' . static::class);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     froq\database\entity\EntityException
     */
    public final function offsetUnset($var)
    {
        throw new EntityException('No unset() allowed for ' . static::class);
    }

    /**
     * Make a deep array from given input.
     *
     * @param  any $in
     * @return array
     * @since  4.11
     */
    protected static function toArrayDeep($in): array
    {
        if ($in && is_object($in)) {
            $out = (array) ($in instanceof Traversable ? iterator_to_array($in) : (
                method_exists($in, 'toArray') ? $in->toArray() : get_object_vars($in)
            ));
        } else {
            $out = (array) $in;
        }

        // Overwrite.
        foreach ($out as $var => $value) {
            if ($value && $value instanceof EntityInterface) {
                $out[$var] = $value->toArray();
                continue;
            }

            $out[$var] = $value && (is_object($value) || is_iterable($value))
                ? self::toArrayDeep($value) : $value;
        }

        return $out;
    }
}
