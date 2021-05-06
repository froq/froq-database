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

    public function isEntity(): bool
    {
        return isset($this->data['entity']);
    }
    public function isEntityList(): bool
    {
        return isset($this->data['entityList']);
    }

    public function isLink(): bool
    {
        return $this->getLinkTable() != null;
    }
    public function getLinkTable(): string|null
    {
        return $this->getDataField('link.to');
    }
    public function getLinkColumn(): string|null
    {
        return $this->getDataField('link.with');
    }
    public function getLinkCondition(): string|null
    {
        return $this->getDataField('link.where');
    }
    public function getLinkLimit(): int|null
    {
        $limit = $this->getDataField('link.limit');

        return intval($limit) ?: null;
    }

    public function packLinkStuff(): array|null
    {
        if ($table = $this->getLinkTable()) {
            return [
                $table,
                $this->getLinkColumn(),
                $this->getLinkCondition(),
                $this->getLinkLimit(),
            ];
        }
        return null;
    }

    // public final function setSetterMethod(string $method): void
    // {
    //     $this->data['@setter'] = $method;
    // }
    // public final function getSetterMethod(): string|null
    // {
    //     return $this->data['@setter'] ?? null;
    // }
    // public final function setGetterMethod(string $method): void
    // {
    //     $this->data['@getter'] = $method;
    // }
    // public final function getGetterMethod(): string|null
    // {
    //     return $this->data['@getter'] ?? null;
    // }
}
