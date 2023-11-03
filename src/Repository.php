<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq
 */
namespace froq\database;

use froq\database\query\{QueryParam, QueryParams};
use froq\database\trait\{DbTrait, EmTrait, RepositoryTrait};
use froq\database\entity\EntityManager;

/**
 * A repository class, provides `$db` and `$em` properties to use in other repository
 * classes and also producers/providers or any other database/entity related classes.
 *
 * @package froq\database
 * @class   froq\database\Repository
 * @author  Kerem Güneş
 * @since   5.2
 */
class Repository
{
    use DbTrait, EmTrait, RepositoryTrait;

    /**
     * Constructor.
     *
     * @param  froq\database\Database|null             $db
     * @param  froq\database\entity\EntityManager|null $em
     * @throws froq\database\RepositoryException
     */
    public function __construct(Database $db = null, EntityManager $em = null)
    {
        if (!$db) try {
            $db = DatabaseRegistry::getDefault();
        } catch (DatabaseRegistryException $e) {
            throw new RepositoryException($e);
        }

        $this->db = $db;
        $this->em = $em ?? new EntityManager($db);
    }

    /**
     * Init a Query instance.
     *
     * @param  string|null $table
     * @return froq\database\Query
     */
    public final function initQuery(string $table = null): Query
    {
        return new Query($this->db, $table);
    }

    /**
     * Init a QueryParam instance.
     *
     * @return froq\database\query\QueryParam
     */
    public final function initQueryParam(): QueryParam
    {
        return new QueryParam();
    }

    /**
     * Init a QueryParams instance.
     *
     * @return froq\database\query\QueryParams
     */
    public final function initQueryParams(): QueryParams
    {
        return new QueryParams();
    }
}
