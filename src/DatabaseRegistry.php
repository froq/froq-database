<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\common\object\Registry;

/**
 * A registry class for pooling default & in case, other database instances.
 *
 * @package froq\database
 * @object  froq\database\DatabaseRegistry
 * @author  Kerem Güneş
 * @since   6.0
 */
final class DatabaseRegistry extends Registry
{
    /** @const string */
    private const DEFAULT_DATABASE_ID = '@default-database';

    /**
     * Check whether default database was set.
     *
     * @return bool
     */
    public static function hasDefault(): bool
    {
        return self::has(self::DEFAULT_DATABASE_ID);
    }

    /**
     * Set default database.
     *
     * @param  froq\database\Database $database
     * @return void
     */
    public static function setDefault(Database $database): void
    {
        self::add(self::DEFAULT_DATABASE_ID, $database);
    }

    /**
     * Get default database or throw a `DatabaseRegistryException` if none
     * was set as default.
     *
     * @param  string $caller @internal
     * @return froq\database\Database
     * @throws froq\database\DatabaseRegistryException
     */
    public static function getDefault(string $caller = null): Database
    {
        if ($database = self::get(self::DEFAULT_DATABASE_ID)) {
            return $database;
        }

        $callerMethod   = $caller;
        $callerArgument = 'db';

        // Hard works..
        if (!$caller) {
            // Try to find caller method & argument from backtrace.
            $trace = new \Trace();
            $match = [__class__, __function__];
            $entry = $trace->find(fn(\TraceEntry $e) => (
                $e->callerMethod && $e->class == $match[0] && $e->function == $match[1]
            ));

            if ($entry) {
                $ref = new \ReflectionCallable($entry->callerMethod);
                foreach ($ref->getParameters() as $ref) {
                    if ($ref->getType()?->getPureName() == Database::class) {
                        $callerMethod   = $entry->callerMethod;
                        $callerArgument = $ref->getName();
                        break;
                    }
                }
            }
        }

        // No caller method.
        if (!$callerMethod) {
            throw new DatabaseRegistryException(
                'No default database was set yet, call %s::setDefault()',
                self::class
            );
        }

        // For internal calls (eg: EntityManager's constructor).
        throw new DatabaseRegistryException(
            'No database to deal yet, call %s::setDefault() or pass $%s argument to %s()',
            [self::class, $callerArgument, $callerMethod]
        );
    }

    /**
     * Add a database instance with given id.
     *
     * @param  string                 $id
     * @param  froq\database\Database $database
     * @param  bool                   $locked
     * @return void
     */
    public static function add(string $id, Database $database, bool $locked = false): void
    {
        self::set($id, $database, $locked);
    }
}
