<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\entity\proxy;

use froq\database\entity\{EntityManager, Entity};
use froq\common\trait\StateTrait;
use ReflectionProperty;

/**
 * A proxy class for entity classes.
 *
 * @package froq\database\entity\proxy
 * @class   froq\database\entity\proxy\EntityProxy
 * @author  Kerem Güneş
 * @since   6.0
 * @internal
 */
class EntityProxy extends Proxy
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
     * Proxy method for manager's `save()` method.
     *
     * @param  froq\database\entity\Entity $entity
     * @return froq\database\entity\Entity
     */
    public function save(Entity $entity): Entity
    {
        return $this->manager->save($entity);
    }

    /**
     * Proxy method for manager's `find()` method.
     *
     * @param  froq\database\entity\Entity $entity
     * @return froq\database\entity\Entity
     */
    public function find(Entity $entity): Entity
    {
        return $this->manager->find($entity);
    }

    /**
     * Proxy method for manager's `remove()` method.
     *
     * @param  froq\database\entity\Entity $entity
     * @return froq\database\entity\Entity
     */
    public function remove(Entity $entity): Entity
    {
        return $this->manager->remove($entity);
    }

    /** Field ref cache (NOT static). */
    private array $refs = [];

    /** Set a field ref as cached. */
    public function setRef(string $field, ReflectionProperty|false $ref): void
    {
        $this->refs[$field] = $ref;
    }

    /** Get a field ref if cached. */
    public function getRef(string $field): ReflectionProperty|false|null
    {
        return $this->refs[$field] ?? null;
    }
}
