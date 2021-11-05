<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{MetaException, Meta, EntityClassMeta, EntityPropertyMeta};

/**
 * Meta.
 *
 * Represents a factory entity that used for creating EntityClassMeta & EntityPropertyMeta.
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
        return self::$cache[$name] ??= match ($type) {
            Meta::TYPE_CLASS    => new EntityClassMeta($class, $data),
            Meta::TYPE_PROPERTY => new EntityPropertyMeta($name, $class, $data),
            default             => throw new MetaException('Invalid type `%s`', $type)
        };
    }

    /**
     * Init a class meta.
     *
     * @param  string     $class
     * @param  array|null $data
     * @return froq\entity\EntityClassMeta
     */
    public static function initEntityClassMeta(string $class, array $data = null): EntityClassMeta
    {
        return self::init(Meta::TYPE_CLASS, $class, $class, $data);
    }

    /**
     * Init a property meta.
     *
     * @param  string     $name
     * @param  string     $class
     * @param  array|null $data
     * @return froq\entity\EntityPropertyMeta
     */
    public static function initEntityPropertyMeta(string $name, string $class, array $data = null):  EntityPropertyMeta
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
