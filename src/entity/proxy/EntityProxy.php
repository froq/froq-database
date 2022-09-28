<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity\proxy;

use froq\database\entity\{EntityManager, Entity};
use froq\common\trait\StateTrait;

/**
 * A proxy class for entity classes.
 *
 * @package froq\database\entity\proxy
 * @object  froq\database\entity\proxy\EntityProxy
 * @author  Kerem Güneş
 * @since   6.0
 * @internal
 */
final class EntityProxy extends Proxy
{
    use StateTrait;

    /**
     * Constructor.
     *
     * @param froq\database\entity\EntityManager|null $manager
     */
    public function __construct(EntityManager $manager = null)
    {
        parent::__construct($manager);

        $this->initState();
    }

    /**
     * Proxy method for manager's save() method.
     *
     * @param  froq\database\entity\Entity $entity
     * @return froq\database\entity\Entity
     */
    public function save(Entity $entity): Entity
    {
        return $this->manager->save($entity);
    }

    /**
     * Proxy method for manager's find() method.
     *
     * @param  froq\database\entity\Entity $entity
     * @return froq\database\entity\Entity
     */
    public function find(Entity $entity): Entity
    {
        return $this->manager->find($entity);
    }

    /**
     * Proxy method for manager's remove() method.
     *
     * @param  froq\database\entity\Entity $entity
     * @return froq\database\entity\Entity
     */
    public function remove(Entity $entity): Entity
    {
        return $this->manager->remove($entity);
    }
}
