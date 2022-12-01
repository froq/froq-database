<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\entity;

use froq\database\entity\proxy\{ProxyTrait, EntityProxy};
use XObject, ReflectionProperty;

/**
 * An abstract entity class that can be extended by entity classes used for accessing
 * & modifiying data via its utility methods such as `save()`, `find()`, `remove()`
 * and checkers for these methods.
 *
 * @package froq\database\entity
 * @class   froq\database\entity\Entity
 * @author  Kerem Güneş
 * @since   5.0
 */
abstract class Entity implements EntityInterface
{
    use ProxyTrait;

    /** Child entity. */
    private XObject $child;

    /**
     * Constructor.
     *
     * @param mixed ...$properties
     */
    public function __construct(mixed ...$properties)
    {
        $this->proxy = new EntityProxy();
        $this->child = new XObject($this);

        $properties && $this->fill(...$properties);
    }

    /**
     * @magic
     */
    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    /**
     * @magic
     */
    public function __serialize(): array
    {
        return [
            '@' => $this->toArray(),
            'states' => $this->proxy->getStates()
        ];
    }

    /**
     * @magic
     */
    public function __unserialize(array $data): void
    {
        $this->proxy = new EntityProxy();
        $this->child = new XObject($this);

        ['@' => $properties, 'states' => $states] = $data;

        $properties && $this->fill(...$properties);

        // Reset states data.
        if ($states) {
            $this->proxy->setStates(...$states);
        }
    }

    /**
     * Run a "save" action.
     *
     * @return self.
     */
    public final function save(): self
    {
        return $this->proxy->save($this);
    }

    /**
     * Run a "find" action.
     *
     * @return self.
     */
    public final function find(): self
    {
        return $this->proxy->find($this);
    }

    /**
     * Run a "remove" action.
     *
     * @return self.
     */
    public final function remove(): self
    {
        return $this->proxy->remove($this);
    }

    /**
     * Check for last "save" action result.
     *
     * @return bool
     */
    public final function isSaved(): bool
    {
        return $this->getActionResult('saved');
    }

    /**
     * Check for last "find" action result.
     *
     * @return bool
     */
    public final function isFinded(): bool
    {
        return $this->getActionResult('finded');
    }

    /**
     * Check for last "remove" action result.
     *
     * @return bool
     */
    public final function isRemoved(): bool
    {
        return $this->getActionResult('removed');
    }

    /**
     * @alias isFinded()
     */
    public final function isFound()
    {
        return $this->isFinded();
    }

    /**
     * Fill entity with given properties.
     *
     * @param  mixed ...$properties
     * @return self
     * @throws froq\database\entity\EntityException
     */
    public final function fill(mixed ...$properties): self
    {
        is_list($properties) && throw new EntityException(
            'Parameter $properties must be an associative array'
        );

        foreach ($properties as $name => $value) {
            if ($ref = $this->getPropertyReflection($name)) {
                $ref->setValue($this, $value);
            }
        }

        return $this;
    }

    /**
     * @inheritDoc froq\common\interface\Arrayable
     */
    public function toArray(bool $deep = false): array
    {
        $data = [];

        foreach ($this->child->getProperties() as $name => $_) {
            if ($ref = $this->getPropertyReflection($name)) {
                $data[$name] = $ref->isInitialized($this) ? $ref->getValue($this) : null;
            }
        }

        $deep && $data = EntityUtil::toDeepArray($data);

        return $data;
    }

    /**
     * @inheritDoc froq\common\interface\Objectable
     */
    public function toObject(bool $deep = false): object
    {
        $data = (object) $this->toArray(false);

        $deep && $data = EntityUtil::toDeepObject($data);

        return $data;
    }

    /**
     * @inheritDoc froq\common\interface\Jsonable
     */
    public function toJson(int $flags = 0, callable $filter = null, callable $map = null): string
    {
        return EntityUtil::toJson($this, $flags, $filter, $map);
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public function offsetExists(mixed $name): bool
    {
        if ($this->getPropertyReflection($name)) {
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public function offsetGet(mixed $name): mixed
    {
        if ($ref = $this->getPropertyReflection($name)) {
            return $ref->isInitialized($this) ? $ref->getValue($this) : null;
        }
        return null;
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public function offsetSet(mixed $name, mixed $value): void
    {
        if ($ref = $this->getPropertyReflection($name)) {
            $ref->setValue($this, $value);
        }
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public function offsetUnset(mixed $name): void
    {
        if ($ref = $this->getPropertyReflection($name)) {
            $ref->setValue($this, null);
        }
    }

    /**
     * Get an action result that stored as state (eg: "saved" for isSaved()).
     */
    private function getActionResult(string $state): bool
    {
        return (bool) $this->proxy->getState($state);
    }

    /**
     * Get a property reflection defined in subclass entity.
     */
    private function getPropertyReflection(string $name): ReflectionProperty|null
    {
        $class = $this->child->getName();

        // Try to get in regular way.
        if ($this->child->hasProperty($name)) {
            $ref = $this->child->reflectProperty($name);

            // Must be same class & non-static.
            if ($ref->class === $class && !$ref->isStatic()) {
                return $ref;
            }
        }

        // Try to get from parsed entity meta, looking for ClassMeta properties.
        // @tome: Do NOT use this method like getMeta(class, property) as it catches EntityManagerException only
        // for empty meta, NOT absent property exceptions (reflection related, so MetaException in MetaParser).
        $propertyMeta = $this->proxy->getManager()?->getMeta($class)?->getPropertyMeta($name);
        if ($propertyMeta) {
            return $propertyMeta->getReflection();
        }

        return null;
    }
}
