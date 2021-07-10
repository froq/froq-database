<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\Meta;
use froq\collection\ItemCollection;

final class EntityClassMeta extends Meta
{
    private array $properties = [];

    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getProperty(string $name): EntityPropertyMeta|null
    {
        return $this->properties[$name] ?? null;
    }
    public function getPropertyNames(): array
    {
        return array_keys($this->properties);
    }

    public function hasSequence(): bool
    {
        return (bool) $this->getOption('sequence', default: true);
    }

    public function getTable(): string|null
    {
        return $this->getDataField('table');
    }
    public function getTablePrimary(): string|array|null
    {
        $ret = $this->getDataField('id', default: 'id');

        if ($ret && strpos($ret, ',')) {
            $ret = split('\s*,\s*', $ret);
        }

        return $ret;
    }

    public function getListClass(): string|null
    {
        return $this->getDataField('list');
    }

    // /**
    //  * @alias of getTablePrimary()
    //  */
    // public function id() { return $this->getTablePrimary(); }

    public function packTableStuff(): array
    {
        return [
            $this->getTable(),
            $this->getTablePrimary(),
        ];
    }

    // public function getForm(): string|null
    // {
    //     return $this->getDataField('form');
    // }
    public function getRecord(): string|null
    {
        return $this->getDataField('record');
    }
}
