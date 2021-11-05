<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{MetaException, Meta, ClassMeta, PropertyMeta};

/**
 * Meta.
 *
 * Represents a factory entity that used for creating ClassMeta & PropertyMeta instances.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\MetaFactory
 * @author  Kerem Güneş
 * @since   5.0
 */
final class MetaFactory
{
    /** @var array */
    private static array $cache = [];

    /**
     * Cache getter.
     *
     * @return array
     */
    public static function cache(): array
    {
        return self::$cache;
    }

    /**
     * Init a meta class.
     *
     * @param  int        $type
     * @param  string     $name
     * @param  string     $class
     * @param  array|null $data
     * @return froq\database\entity\Meta
     * @throws froq\database\entity\MetaException
     */
    public static function init(int $type, string $name, string $class, array $data = null): Meta
    {
        $name = Meta::prepareName($type, $name, $class);

        return self::$cache[$name] ??= match ($type) {
            Meta::TYPE_CLASS    => new ClassMeta($class, $data),
            Meta::TYPE_PROPERTY => new PropertyMeta($name, $class, $data),
            default             => throw new MetaException('Invalid type `%s`', $type)
        };
    }

    /**
     * Init a class meta.
     *
     * @param  string     $class
     * @param  array|null $data
     * @return froq\entity\ClassMeta
     */
    public static function initClassMeta(string $class, array $data = null): ClassMeta
    {
        return self::init(Meta::TYPE_CLASS, $class, $class, $data);
    }

    /**
     * Init a property meta.
     *
     * @param  string     $name
     * @param  string     $class
     * @param  array|null $data
     * @return froq\entity\PropertyMeta
     */
    public static function initPropertyMeta(string $name, string $class, array $data = null):  PropertyMeta
    {
        return self::init(Meta::TYPE_PROPERTY, $name, $class, $data);
    }

    /**
     * Check a cache item.
     *
     * @param  string $name
     * @return bool
     */
    public static function hasCacheItem(string $name): bool
    {
        return isset(self::$cache[$name]);
    }

    /**
     * Set a cache item.
     *
     * @param  string                    $name
     * @param  froq\database\entity\Meta $item
     * @return void
     */
    public static function setCacheItem(string $name, Meta $item): void
    {
        self::$cache[$name] = $item;
    }

    /**
     * Get a cache item.
     *
     * @param  string                         $name
     * @return froq\database\entity\Meta|null $item
     */
    public static function getCacheItem(string $name): Meta|null
    {
        return self::$cache[$name] ?? null;
    }

    /**
     * Delete a cache item.
     *
     * @param  string $name
     * @return void
     */
    public static function delCacheItem(string $name): void
    {
        unset(self::$cache[$name]);
    }
}
