<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\collection\ItemCollection;
use froq\pager\Pager;

/**
 * Record List.
 *
 * A list class, used internally and holds `$pager` property and its getter method, and also
 * all basic collection methods such as `filter()`, `map(), `reduce()` etc.
 *
 * @package froq\database\record
 * @object  froq\database\record\RecordList
 * @author  Kerem Güneş
 * @since   5.0
 */
class RecordList extends ItemCollection implements RecordListInterface
{
    /** @var froq\pager\Pager|null */
    protected Pager|null $pager;

    /**
     * Constructor.
     *
     * @param array                 $items
     * @param froq\pager\Pager|null $pager
     * @param bool|null             $readOnly
     */
    public function __construct(array $items, Pager $pager = null, bool $readOnly = null)
    {
        $this->pager = $pager;

        foreach ($items as $item) {
            ($item instanceof Record) || throw new RecordListException(
                'Each item must extend class %s, %t given', [Record::class, $item]
            );
        }

        // State "readOnly" can be changed calling readOnly() or lock()/unlock().
        parent::__construct($items, readOnly: $readOnly);
    }

    /**
     * Get pager property.
     *
     * @return froq\pager\Pager|null
     */
    public final function pager(): Pager|null
    {
        return $this->pager;
    }

    /**
     * Get a array copy of data items.
     *
     * @return array<int, array>
     */
    public final function data(): array
    {
        return $this->toArray(true);
    }

    /** @override */
    public final function toArray(bool $deep = true): array
    {
        if ($deep) {
            $items = [];
            foreach ($this->items() as $item) {
                $items[] = ($item instanceof Record) ? $item->toArray() : $item;
            }
        } else {
            $items = parent::toArray();
        }

        return $items;
    }
}
