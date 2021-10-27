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
    private Pager|null $pager = null;

    public function __debugInfo()
    {
        $ret = (array) $this;

        // Drop (self) record property. @temp?
        unset($ret["\0" . self::class . "\0pager"]);

        return $ret;
    }

    // public final function setOwner(AbstractEntity|AbstractEntityList $owner): static
    public final function setOwner($owner): static
    {
        $this->owner = $owner;

        return $this;
    }
    public final function getOwner()//: AbstractEntity|AbstractEntityList|null
    {
        return $this->owner ?? null;
    }

    public final function setPager(Pager $pager): static
    {
        $this->pager = $pager;

        return $this;
    }
    public final function getPager(): Pager|null
    {
        return $this->pager;
    }
}
