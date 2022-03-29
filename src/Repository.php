<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\{sql\Sql, entity\Manager as EntityManager};
use froq\database\trait\{DbTrait, EmTrait, TableTrait, ValidationTrait};

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
    use DbTrait, EmTrait, TableTrait, ValidationTrait;

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
}
