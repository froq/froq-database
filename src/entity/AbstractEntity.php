<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\Manager;
use froq\database\record\Record;

abstract class AbstractEntity
{
    private Manager $manager;
    private Record $record;

    public function __debugInfo()
    {
        [$data, $class] = [(array) $this, self::class];

        // Drop internals.
        unset($data["\0{$class}\0manager"]);
        unset($data["\0{$class}\0record"]);

        return $data;
    }

    public final function fill(...$properties): static
    {
        foreach ($properties as $name => $value) {
            if (property_exists(static::class, $name)) {
                $this->{$name} = $value;
            }
        }

        return $this;
    }

    public final function setManager(Manager $manager): static
    {
        $this->manager = $manager;

        return $this;
    }
    public final function getManager(): Manager|null
    {
        return $this->manager ?? null;
    }

    public final function setRecord(Record $record): static
    {
        $this->record = $record;

        return $this;
    }
    public final function getRecord(): Record
    {
        return $this->record;
    }

    public final function isSaved(): bool
    {
        return $this->record->isSaved();
    }
    public final function isFinded(): bool
    {
        return $this->record->isFinded();
    }
    public final function isRemoved(): bool
    {
        return $this->record->isRemoved();
    }
}
