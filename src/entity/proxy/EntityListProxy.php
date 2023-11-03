<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\entity\proxy;

use froq\database\entity\{EntityManager, EntityList};

/**
 * A proxy class for entity list classes.
 *
 * @package froq\database\entity\proxy
 * @class   froq\database\entity\proxy\EntityListProxy
 * @author  Kerem Güneş
 * @since   6.0
 * @internal
 */
class EntityListProxy extends Proxy
{
    /**
     * Constructor.
     *
     * @param froq\database\entity\EntityManager|null $manager
     */
    public function __construct(EntityManager $manager = null)
    {
        parent::__construct($manager);
    }

    /**
     * Proxy method for manager's `saveAll()` method.
     *
     * @param  froq\database\entity\EntityList $entityList
     * @return froq\database\entity\EntityList
     */
    public function saveAll(EntityList $entityList): EntityList
    {
        return $this->manager->saveAll($entityList);
    }

    /**
     * Proxy method for manager's `findAll()` method.
     *
     * @param  froq\database\entity\EntityList $entityList
     * @return froq\database\entity\EntityList
     */
    public function findAll(EntityList $entityList): EntityList
    {
        return $this->manager->findAll($entityList);
    }

    /**
     * Proxy method for manager's `removeAll()` method.
     *
     * @param  froq\database\entity\EntityList $entityList
     * @return froq\database\entity\EntityList
     */
    public function removeAll(EntityList $entityList): EntityList
    {
        return $this->manager->removeAll($entityList);
    }
}
