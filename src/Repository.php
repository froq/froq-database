<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\{sql\Sql, entity\Manager as EntityManager};
use froq\database\trait\{DbTrait, TableTrait, ValidationTrait, EntityManagerTrait};

/**
 * Repository.
 *
 * A class, contains most required data read/write tools and is intended to use other
 * repository classes and producers/providers or other database related classes.
 *
 * @package froq\database
 * @object  froq\database\Repository
 * @author  Kerem Güneş
 * @since   5.2
 */
class Repository
{
    /**
     * @see froq\database\trait\DbTrait
     * @see froq\database\trait\TableTrait
     * @see froq\database\trait\ValidationTrait
     * @see froq\database\trait\EntityManagerTrait
     */
    use DbTrait, TableTrait, ValidationTrait, EntityManagerTrait;

    /**
     * Constructor.
     *
     * @param  froq\database\Database|null       $db
     * @param  froq\database\entity\Manager|null $em
     * @throws froq\database\RepositoryException
     */
    public function __construct(Database $db = null, EntityManager $em = null)
    {
        // Try to use active app database object.
        $db ??= function_exists('app') ? app()->database() : throw new RepositoryException(
            'No database given to deal, be sure running this module with `froq\app` '.
            'module and be sure `database` option exists in app config or pass $db argument'
        );

        $this->db = $db;
        $this->em = $em ?? new EntityManager($db);
    }

    /**
     * Init a `Sql` object with/without given params argument.
     *
     * @param  string     $input
     * @param  array|null $params
     * @return froq\database\sql\Sql
     */
    public final function sql(string $input, array $params = null): Sql
    {
        return $this->db->initSql($input, $params);
    }

    /**
     * Init a `Query` object using self `$db` property, setting its "table" query with `$table` argument
     * when provided or using self `$table` property.
     *
     * @param  string|null $table
     * @return froq\database\Query
     */
    public final function query(string $table = null): Query
    {
        return $this->db->initQuery($table ?? $this->table ?? null);
    }
}
