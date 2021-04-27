<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\EntityException;
use froq\common\interface\Arrayable;
use froq\collection\Collection;
use Countable, JsonSerializable, ArrayAccess, IteratorAggregate, ArrayIterator, Traversable, Error;

/**
 * Entity.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\Entity
 * @author  Kerem Güneş
 * @since   4.2, 5.0 Dropped abstract-ness.
 */
class Entity implements Arrayable, Countable, JsonSerializable, ArrayAccess, IteratorAggregate
{
    /**
     * Constructor.
     *
     * @param array|null      $data
     * @param array|bool|null $drop
     * @param bool            $clean
     */
    public function __construct(array $data = null, array|bool $drop = null, bool $clean = false)
    {
        $data ??= [];

        // Drop unused/unwanted vars.
        if ($drop) {
            // Unused vars (all nulls).
            if ($drop === true) {
                $vars = $this->getVarNames();
                $diff = count($vars) > count($data)
                      ? array_diff($vars, array_keys($data))
                      : array_diff(array_keys($data), $vars);
            }
            // Unwanted vars (list).
            else {
                $diff = $drop;
            }

            // Drop unused/unwanted vars.
            foreach ($diff as $var) {
                if (!isset($data[$var]) /* eg: id=null */
                    || !!$drop          /* eg: drop=['id'] */) {
                    unset($data[$var]);
                }
            }
        }

        // Set vars.
        foreach ($data as $var => $value) {
            $this->{$var} = $value;
        }

        // Clean null vars.
        $clean && $this->clean();
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
        // First set all vars (PHP creates new object with all vars).
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
     * Set one var.
     *
     * @param  string $vars
     * @return self
     * @since  5.0
     */
    public final function set(string $var, $value): self
    {
        $this->__set($var, $value);

        return $this;
    }

    /**
     * Set many vars.
     *
     * @param  array $vars
     * @return self
     * @since  5.0
     */
    public final function setVars(array $vars): self
    {
        foreach ($vars as $var => $value) {
            $this->__set($var, $value);
        }

        return $this;
    }

    /**
     * Get a var value.
     *
     * @param  string $var
     * @return any|null
     * @since  4.11
     */
    public final function get(string $var, $default = null)
    {
        return $this->__get($var) ?? $default;
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
        $all || $vars = array_filter($vars, fn($v) => $v[0] != '_', 2);

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
        return array_values($this->getVars($all));
    }

    /**
     * Select one var / many vars.
     *
     * @param  string|array $vars
     * @return array
     * @since  5.0
     */
    public final function select(string|array $vars): array
    {
        foreach ((array) $vars as $var) {
            $ret[$var] = $this->{$var} ?? null;
        }

        return $ret;
    }

    /**
     * Drop one var / many vars.
     *
     * @param  string|array<string> $vars
     * @return self
     * @since  5.0
     */
    public final function drop(string|array $vars): self
    {
        foreach ((array) $vars as $var) {
            unset($this->{$var});
        }

        return $this;
    }

    /**
     * Id, shortcut for IDs.
     *
     * @return int|string|null
     */
    public function id()
    {
        return $this->get('id');
    }

    /**
     * Empty entity dropping all vars.
     *
     * @return self
     * @since  5.0
     */
    public function empty(): self
    {
        foreach ($this->getVarNames() as $var) {
            unset($this->{$var});
        }

        return $this;
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
     * Filter vars.
     *
     * @param  callable|null $func
     * @return self
     * @since  4.12
     */
    public function filter(callable $func = null): self
    {
        $filtered = array_filter(
            $vars = $this->getVars(),
            $func ?? fn($v) => $v !== null, // Filter nulls only.
        );

        foreach (array_keys($vars) as $var) {
            if (!isset($filtered[$var])) {
                unset($this->{$var});
            }
        }

        return $this;
    }

    /**
     * Clean null vars.
     *
     * @return self
     * @since  5.0
     */
    public function clean(): self
    {
        foreach ($this->getVarNames() as $var) {
            if (!isset($this->{$var})) {
                unset($this->{$var});
            }
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
        return !$deep ? $this->getVars() : self::toArrayDeep($this->getVars());
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
                $in instanceof self ? $in->toArray(true) : get_object_vars($in)
            ));
        } else {
            $out = (array) $in;
        }

        // Overwrite.
        foreach ($out as $var => $value) {
            if ($value && $value instanceof self) {
                $out[$var] = $value->toArray(true);
                continue;
            }

            $out[$var] = $value && (is_object($value) || is_iterable($value))
                ? self::toArrayDeep($value) : $value;
        }

        return $out;
    }
}
