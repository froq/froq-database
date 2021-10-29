<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\Meta;

final class EntityPropertyMeta extends Meta
{
    public function getEntityClass(): string|null
    {
        return $this->getDataField('entity');
    }
    public function getEntityListClass(): string|null
    {
        return $this->getDataField('entityList');
    }
    public function getRepositoryClass(): string|null
    {
        return $this->getDataField('repository');
    }

    public function getValue(object $object)
    {
        return $this->getReflector()->getValue($object);
    }

    public function getValidation(): array|null
    {
        return $this->getDataField('validation');
    }
    public function getValidationDefault()
    {
        return $this->getDataField('validation.default');
    }

    public function hasEntity(): bool
    {
        return !empty($this->data['entity']);
    }
    public function hasEntityList(): bool
    {
        return !empty($this->data['entityList']);
    }

    public function isLinked(): bool
    {
        return !empty($this->data['link']);
    }
    public function getLinkedTable(): string|null
    {
        return $this->getDataField('link.table');
    }
    public function getLinkedColumn(): string|null
    {
        return $this->getDataField('link.column');
    }
    public function getLinkedCondition(): string|null
    {
        return $this->getDataField('link.where');
    }
    public function getLinkedMethod(): string|null
    {
        return $this->getDataField('link.method');
    }

    public function getLinkedLimit(): int|null
    {
        $limit = $this->getDataField('link.limit');

        return intval($limit) ?: null;
    }

    public function getLinkedCascade(): string|bool
    {
        return $this->getDataField('link.cascade', default: null);
    }
    public function isLinkedCascadesFor(string $action): bool
    {
        $cascade = $this->getLinkedCascade();

        // Asterisk allows both "true" and "*" arguments.
        return $cascade && ($cascade == '*' || str_contains($cascade, $action));
    }

    public function packLinkStuff(): array|null
    {
        if ($table = $this->getLinkedTable()) {
            return [
                $table,
                $this->getLinkedColumn(),
                $this->getLinkedCondition(),
                $this->getLinkedMethod() ?: 'one-to-one', // As default.
                $this->getLinkedLimit(),
            ];
        }
        return null;
    }
}
