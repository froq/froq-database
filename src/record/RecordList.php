<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\pager\{Pager, PagerTrait};

/**
 * A list class, for collecting `Record` instances.
 *
 * @package froq\database\record
 * @object  froq\database\record\RecordList
 * @author  Kerem Güneş
 * @since   5.0
 */
class RecordList extends \ItemList implements RecordListInterface
{
    use PagerTrait;

    /**
     * Constructor.
     *
     * @param array                 $items
     * @param froq\pager\Pager|null $pager
     */
    public function __construct(array $items = [], Pager $pager = null)
    {
        parent::__construct($items);

        $this->pager = $pager;
    }

    /**
     * @override
     */
    public function toArray(bool $deep = false): array
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
}
