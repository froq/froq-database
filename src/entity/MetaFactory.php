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

    public static function init(int $type, array $data, string $name, string $class): Meta
    {
        // Cache key.
        $key = join('@', [$type, $name, $class]);

        self::$cache[$key] = match ($type) {
            Meta::TYPE_CLASS    => new EntityClassMeta($type, $data, $name, $class),
            Meta::TYPE_PROPERTY => new EntityPropertyMeta($type, $data, $name, $class),
            default             => throw new MetaException('Invalid type `%s`', $type)
        };

        return self::$cache[$key];
    }
}
