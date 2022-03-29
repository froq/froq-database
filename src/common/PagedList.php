<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\common;

use froq\pager\Pager;
use ItemList;

/**
 * A list class, for the classes using `Pager` stuff.
 *
 * @package froq\database\common
 * @object  froq\database\common\PagedList
 * @author  Kerem Güneş
 * @since   6.0
 */
class PagedList extends ItemList
{
    /** @var froq\pager\Pager|null */
    protected Pager|null $pager;

    /**
     * Constructor.
     *
     * @param array                 $items
     * @param froq\pager\Pager|null $pager
     * @param string|null           $type
     * @param bool                  $locked
     */
    public function __construct(array $items = [], Pager $pager = null, string $type = null, bool $locked = false)
    {
        parent::__construct($items, $type, $locked);

        $this->pager = $pager;
    }

    /**
     * Set pager property.
     *
     * @param  froq\pager\Pager $pager
     * @return self
     */
    public final function setPager(Pager $pager): self
    {
        $this->pager = $pager;

        return $this;
    }

    /**
     * Get pager property.
     *
     * @return froq\pager\Pager|null
     */
    public final function getPager(): Pager|null
    {
        return $this->pager;
    }
}
