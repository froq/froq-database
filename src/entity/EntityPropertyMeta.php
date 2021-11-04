<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\Meta;

/**
 * Entity Property Meta.
 *
 * Represents a metadata class entity that keeps a parsed property metadata details.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\EntityPropertyMeta
 * @author  Kerem Güneş
 * @since   5.0
 */
final class EntityPropertyMeta extends Meta
{
    /**
     * Get entity class.
     *
     * @return string|null
     */
    public function getEntityClass(): string|null
    {
        return $this->getDataField('entity');
    }

    /**
     * Get entity list class.
     *
     * @return string|null
     */
    public function getEntityListClass(): string|null
    {
        return $this->getDataField('entityList');
    }

    /**
     * Get repository class.
     *
     * @return string|null
     */
    public function getRepositoryClass(): string|null
    {
        return $this->getDataField('repository');
    }

    /**
     * Get propert value using reflector.
     *
     * @return any|null
     */
    public function getValue(object $object)
    {
        return $this->getReflector()->getValue($object);
    }

    /**
     * Get validation options.
     *
     * @return array|null
     */
    public function getValidation(): array|null
    {
        return $this->getDataField('validation');
    }

    /**
     * Get validation default option for empty/null situations.
     *
     * @return any|null
     */
    public function getValidationDefault()
    {
        return $this->getDataField('validation.default');
    }

    /**
     * Check whether has entity class.
     *
     * @return bool
     */
    public function hasEntity(): bool
    {
        return !empty($this->data['entity']);
    }

    /**
     * Check whether has entity list class.
     *
     * @return bool
     */
    public function hasEntityList(): bool
    {
        return !empty($this->data['entityList']);
    }

    /**
     * Check whether is linked.
     *
     * @return bool
     */
    public function isLinked(): bool
    {
        return !empty($this->data['link']);
    }

    /**
     * Get link table.
     *
     * @return string|null
     */
    public function getLinkTable(): string|null
    {
        return $this->getDataField('link.table');
    }

    /**
     * Get link column.
     *
     * @return string|null
     */
    public function getLinkColumn(): string|null
    {
        return $this->getDataField('link.column');
    }

    /**
     * Get link condition.
     *
     * @return string|null
     */
    public function getLinkCondition(): string|null
    {
        return $this->getDataField('link.where');
    }

    /**
     * Get link method.
     *
     * @return string|null
     */
    public function getLinkMethod(): string|null
    {
        return $this->getDataField('link.method');
    }

    /**
     * Get link limit.
     *
     * @return int|null
     */
    public function getLinkLimit(): int|null
    {
        $limit = $this->getDataField('link.limit');

        return intval($limit) ?: null;
    }

    /**
     * Check whether linked table cascades for given action.
     *
     * @param  string $action
     * @return bool
     */
    public function isLinkCascadesFor(string $action): bool
    {
        $cascade = $this->getDataField('link.cascade', default: null);

        // Asterisk allows both "true" and "*" arguments.
        return $cascade && ($cascade == '*' || str_contains($cascade, $action));
    }

    /**
     * Pack table stuff.
     *
     * @return array|null
     * @internal
     */
    public function packLinkStuff(): array|null
    {
        if ($table = $this->getLinkTable()) {
            return [
                $table,
                $this->getLinkColumn(),
                $this->getLinkCondition(),
                $this->getLinkMethod() ?? 'one-to-one', // As default.
                $this->getLinkLimit(),
            ];
        }

        return null;
    }
}
