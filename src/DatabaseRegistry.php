<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database;

use froq\common\object\Registry;
use TraceStack, Trace, ReflectionCallable;

/**
 * A registry class for pooling default & in case, other database instances.
 *
 * @package froq\database
 * @class   froq\database\DatabaseRegistry
 * @author  Kerem Güneş
 * @since   6.0
 */
class DatabaseRegistry extends Registry
{
    /** Default database id. */
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
            $match = [__CLASS__, __FUNCTION__];
            $trace = (new TraceStack)->find(fn(Trace $t): bool => (
                   $t->callerMethod !== null
                && $t->class        === $match[0]
                && $t->function     === $match[1]
            ));

            if ($trace) {
                $ref = new ReflectionCallable($trace->callerMethod);
                foreach ($ref->getParameters() as $ref) {
                    if ($ref->getType()?->getPureName() === Database::class) {
                        $callerMethod   = $trace->callerMethod;
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
