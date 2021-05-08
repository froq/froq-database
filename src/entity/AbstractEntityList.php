<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\record\Record;
use froq\collection\ItemCollection;
use froq\pager\Pager;

abstract class AbstractEntityList extends ItemCollection
{
    // private AbstractEntity|AbstractEntityList $owner;
    private $owner;
    private object|null $pager = null;

    public function __debugInfo()
    {
        $ret = (array) $this;

        // Drop (self) record property. @temp?
        unset($ret["\0" . self::class . "\0pager"]);

        return $ret;
    }

    // public final function setOwner(AbstractEntity|AbstractEntityList $owner): void
    public final function setOwner($owner): void
    {
        $this->owner = $owner;
    }
    public final function getOwner()//: AbstractEntity|AbstractEntityList|null
    {
        return $this->owner ?? null;
    }

    public final function setPager(Pager $pager): void
    {
        // Wrap pager for brevity & public access,
        // and to set all dynamic vars eg. limit, offset etc.
        $this->pager = new class($pager) {
            public function __construct($pager) {
                foreach ($pager->toArray() as $name => $value) {
                    $this->$name = $value;
                }
            }
        };
    }
    public final function getPager(): Pager|null
    {
        return $this->pager;
    }
}
