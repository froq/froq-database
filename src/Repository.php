<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\trait\{DbTrait, EmTrait};
use froq\database\{common\Helper, entity\Manager as EntityManager};

/**
 * A class, intended to use other repository classes and producers/providers
 * or other database related classes.
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
     * @param  froq\database\Database|null       $db
     * @param  froq\database\entity\Manager|null $em
     * @throws froq\database\RepositoryException
     */
    public function __construct(Database $db = null, EntityManager $em = null)
    {
        if (!$db) try {
            $db = Helper::getActiveDatabase();
        } catch (DatabaseException $e) {
            throw new RepositoryException($e->message);
        }

        $this->db = $db;
        $this->em = $em ?? new EntityManager($db);
    }
}
