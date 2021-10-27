<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\record\Record;

abstract class AbstractEntity
{
    // private AbstractEntity $owner;
    private $owner;
    private Record $record;

    public function __debugInfo()
    {
        $ret = (array) $this;

        // Drop (self) record property. @temp?
        unset($ret["\0" . self::class . "\0record"]);

        return $ret;
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

    // public final function setOwner(AbstractEntity $owner): static
    public final function setOwner($owner): static
    {
        $this->owner = $owner;

        return $this;
    }
    public final function getOwner()//: AbstractEntity|null
    {
        return $this->owner ?? null;
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
