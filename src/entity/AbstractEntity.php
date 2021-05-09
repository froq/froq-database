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

    public final function fill(...$args)
    {
        // When an array given as first argument.
        if (isset($args[0]) && is_array($args[0])) {
            $args = $args[0];
        }

        foreach ($args as $name => $value) {
            if (property_exists(static::class, $name)) {
                $this->{$name} = $value;
            }
        }
    }

    // public final function setOwner(AbstractEntity $owner): void
    public final function setOwner($owner): void
    {
        $this->owner = $owner;
    }
    public final function getOwner()//: AbstractEntity|null
    {
        return $this->owner ?? null;
    }

    public final function setRecord(Record $record): void
    {
        $this->record = $record;
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
