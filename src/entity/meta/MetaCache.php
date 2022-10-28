<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity\meta;

use froq\cache\agent\Apcu;

/**
 * A cache class, used for storing parsed meta.
 *
 * @package froq\database\entity\meta
 * @object  froq\database\entity\meta\MetaCache
 * @author  Kerem Güneş
 * @since   6.1
 */
final class MetaCache
{
    /**
     * Fallback storage if no APCu available.
     *
     * @var array<froq\database\entity\meta\Meta>
     */
    private static array $items = [];

    /**
     * Getter of `$items` property.
     *
     * @return array<froq\database\entity\meta\Meta>
     */
    public static function items(): array
    {
        return self::$items;
    }

    /**
     * Check an item.
     *
     * @param  string $name
     * @return bool
     */
    public static function hasItem(string $name): bool
    {
        if ($apc = self::apc()) {
            [$apc, $key] = $apc;
            return $apc->has($key . $name);
        }
        return isset(self::$items[$name]);
    }

    /**
     * Set an item.
     *
     * @param  string                         $name
     * @param  froq\database\entity\meta\Meta $item
     * @return void
     */
    public static function setItem(string $name, Meta $item): void
    {
        if ($apc = self::apc()) {
            [$apc, $key] = $apc;
            $apc->set($key . $name, $item);
            return;
        }
        self::$items[$name] = $item;
    }

    /**
     * Get an item.
     *
     * @param  string $name
     * @return froq\database\entity\meta\Meta|null
     */
    public static function getItem(string $name): Meta|null
    {
        if ($apc = self::apc()) {
            [$apc, $key] = $apc;
            return $apc->get($key . $name);
        }
        return self::$items[$name] ?? null;
    }

    /**
     * Delete an item.
     *
     * @param  string $name
     * @return void
     */
    public static function deleteItem(string $name): void
    {
        if ($apc = self::apc()) {
            [$apc, $key] = $apc;
            $apc->delete($key . $name);
            return;
        }
        unset(self::$items[$name]);
    }

    /**
     * Clear items.
     *
     * @return void
     */
    public static function clear(): void
    {
        if ($apc = self::apc()) {
            [$apc, $key] = $apc;
            $apc->clear($key);
            return;
        }
        self::$items = [];
    }

    /**
     * Create Apcu instance if available, return apc instance and key prefix.
     */
    private static function apc(): array|null
    {
        static $apc, $key = '_META_';

        if ($apc === null) {
            // Not for CLI & CLI Server.
            if (str_starts_with(PHP_SAPI, 'cli')) {
                return null;
            }

            // Apcu throws exception.
            if (!extension_loaded('apcu')) {
                return null;
            }

            $apc = new Apcu();
        }

        return $apc ? [$apc, $key] : null;
    }
}
