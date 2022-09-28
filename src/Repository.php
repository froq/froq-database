<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\trait\{DbTrait, EmTrait};
use froq\database\entity\EntityManager;

/**
 * A repository class, provides `$db` and `$em` properties, to use in other repository
 * classes and also producers/providers or any other database/entity related classes.
 *
 * @package froq\database
 * @object  froq\database\Repository
 * @author  Kerem Güneş
 * @since   5.2
 */
class Repository
{
    use DbTrait, EmTrait;

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
}
