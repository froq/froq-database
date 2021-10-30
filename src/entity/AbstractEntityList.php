<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\Manager;
use froq\database\record\Record;
use froq\collection\ItemCollection;
use froq\pager\Pager;

abstract class AbstractEntityList extends ItemCollection
{
    private Manager $manager;
    private Pager|null $pager = null;

    public function __debugInfo()
    {
        [$data, $class] = [(array) $this, self::class];

        // Drop internals.
        unset($data["\0{$class}\0manager"]);
        unset($data["\0{$class}\0pager"]);

        return $data;
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
