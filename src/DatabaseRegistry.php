<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

/**
 * A registry class for pooling default & (in case) other database instances.
 *
 * @package froq\database
 * @object  froq\database\DatabaseRegistry
 * @author  Kerem Güneş
 * @since   6.0
 */
final class DatabaseRegistry
{
    /** @const string */
    private const DEFAULT = '@default';

    /** @var array<froq\database\Database> */
    private static array $databases = [];

    /**
     * Check whether default database was set.
     *
     * @return bool
     */
    public static function hasDefault(): bool
    {
        return self::get(self::DEFAULT) != null;
    }

    /**
     * Set default database.
     *
     * @param  froq\database\Database $database
     * @return void
     */
    public static function setDefault(Database $database): void
    {
        self::add(self::DEFAULT, $database);
    }

    /**
     * Get default database or throw a `DatabaseRegistryException` if none was set.
     *
     * @param  string $caller @internal
     * @return void
     * @throws froq\database\DatabaseRegistryException
     */
    public static function getDefault(string $caller = null): Database
    {
        if ($database = self::get(self::DEFAULT)) {
            return $database;
        }

        if (!$caller) {
            throw new DatabaseRegistryException(
                'No default database was set yet, call %s::setDefault()',
                self::class
            );
        }

        // For internal calls (eg: entity Manager's constructor).
        throw new DatabaseRegistryException(
            'No database given to deal. Call %s::setDefault() method '.
            'first or pass $db argument to %s()',
            [self::class, $caller]
        );
    }

    /**
     * Add a database instance with given id.
     *
     * @param  string                 $id
     * @param  froq\database\Database $database
     * @return void
     */
    public static function add(string $id, Database $database): void
    {
        self::$databases[$id] = $database;
    }

    /**
     * Get a database instance by given id.
     *
     * @param  string $id
     * @return froq\database\Database|null
     */
    public static function get(string $id): Database|null
    {
        return self::$databases[$id] ?? null;
    }

    /**
     * Remove a database instance by given id.
     *
     * @param  string $id
     * @return void
     */
    public static function remove(string $id): void
    {
        unset(self::$databases[$id]);
    }
}
