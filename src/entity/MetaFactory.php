<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{MetaException, Meta};

class MetaFactory
{
    private static array $cache;

    public static function init(int $type, string $name, string $class, array $data = null): Meta
    {
        return self::$cache[$name] ??= match ($type) {
            Meta::TYPE_CLASS    => new EntityClassMeta($type, $name, $class, $data),
            Meta::TYPE_PROPERTY => new EntityPropertyMeta($type, $name, $class, $data),
            default             => throw new MetaException('Invalid type `%s`', $type)
        };
    }

    public static function hasCacheItem(string $name): bool
    {
        return isset(self::$cache[$name]);
    }
    public static function setCacheItem(string $name, Meta $item): void
    {
        self::$cache[$name] = $item;
    }
    public static function getCacheItem(string $name): Meta|null
    {
        return self::$cache[$name] ?? null;
    }
}
