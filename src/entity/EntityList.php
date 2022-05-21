<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\proxy\{ProxyTrait, EntityListProxy};
use froq\pager\{Pager, PagerTrait};

/**
 * An abstract entity list class that can be extended by entity list classes used for
 * accessing & modifiying data via its utility methods such as `saveAll()`, `findAll()`,
 * `removeAll()` and checkers for these methods.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\EntityList
 * @author  Kerem Güneş
 * @since   5.0
 */
abstract class EntityList extends \ItemList implements EntityListInterface
{
    use ProxyTrait, PagerTrait;

    /**
     * Constructor.
     *
     * @param object ...$entities
     */
    public function __construct(object ...$entities)
    {
        $entities && $this->fill(...$entities);

        $this->proxy = new EntityListProxy();
    }

    /** @magic */
    public function __serialize(): array
    {
        return ['@' => $this->toArray(), 'pager' => $this->pager?->toArray()];
    }

    /** @magic */
    public function __unserialize(array $data): void
    {
        ['@' => $entities, 'pager' => $pager] = $data;

        $entities && $this->fill(...$entities);

        // Reset proxy with pager data.
        $this->proxy = new EntityListProxy();
        if ($pager) {
            $this->pager = new Pager((array) $pager);
            $this->pager->run();
        }
    }

    /**
     * Run a "save-all" action.
     *
     * @return self
     */
    public final function saveAll(): self
    {
        return $this->proxy->saveAll($this);
    }

    /**
     * Run a "find-all" action.
     *
     * @return self
     */
    public final function findAll(): self
    {
        return $this->proxy->findAll($this);
    }

    /**
     * Run a "remove-all" action.
     *
     * @return self
     */
    public final function removeAll(): self
    {
        return $this->proxy->removeAll($this);
    }

    /**
     * Check for last "save-all" action result.
     *
     * @return bool
     */
    public final function isSavedAll(): bool
    {
        return $this->getActionResult('isSaved');
    }

    /**
     * Check for last "find-all" action result.
     *
     * @return bool
     */
    public final function isFindedAll(): bool
    {
        return $this->getActionResult('isFinded');
    }

    /**
     * Check for last "remove-all" action result.
     *
     * @return bool
     */
    public final function isRemovedAll(): bool
    {
        return $this->getActionResult('isRemoved');
    }

    /**
     * @alias isFindedAll()
     */
    public final function isFoundAll(): bool
    {
        return $this->isFindedAll();
    }

    /**
     * Fill entity list with given entities.
     *
     * @param  object ...$entities
     * @return self
     * @throws froq\database\entity\EntityListException
     */
    public final function fill(object ...$entities): self
    {
        is_list($entities) || throw new EntityListException(
            'Parameter $entities must be a list array'
        );

        foreach ($entities as $entity) {
            $this->add($entity);
        }

        return $this;
    }

    /**
     * @override
     */
    public function toArray(bool $deep = false): array
    {
        $items = parent::toArray();

        $deep && $items = EntityUtil::toDeepArray($items);

        return $items;
    }

    /**
     * @override
     */
    public function toJson(int $flags = 0, callable $filter = null, callable $map = null): string
    {
        return EntityUtil::toJson($this, $flags, $filter, $map);
    }

    /**
     * Get action result filtering items by given action.
     */
    private function getActionResult(string $action): bool
    {
        return count($this->items()) == count(array_filter(
            $this->items(), fn($entity) => $entity->$action()
        ));
    }
}
