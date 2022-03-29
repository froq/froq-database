<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\database\common\PagedList;
use froq\pager\Pager;

/**
 * A list class, for collecting `Record` instances.
 *
 * @package froq\database\record
 * @object  froq\database\record\RecordList
 * @author  Kerem Güneş
 * @since   5.0
 */
class RecordList extends PagedList implements RecordListInterface
{
    /**
     * Constructor.
     *
     * @param array                 $items
     * @param froq\pager\Pager|null $pager
     * @param bool                  $locked
     */
    public function __construct(array $items = [], Pager $pager = null, bool $locked = false)
    {
        parent::__construct($items, $pager, type: $this->extractType(), locked: $locked);
    }

    /**
     * @override
     */
    public final function toArray(bool $deep = false): array
    {
        $items = parent::toArray();

        if ($deep) {
            foreach ($items as &$item) {
                if ($item instanceof Record) {
                    $item = $item->toArray();
                }
            }
        }

        return $items;
    }

    /**
     * Extract accepting type if available, or return `Record` class as default.
     */
    private function extractType(): string
    {
        $type = substr(static::class, 0, -strlen('List'));

        if (!class_exists($type) || !class_extends($type, Record::class)) {
            $type = Record::class;
        }

        return $type;
    }
}
