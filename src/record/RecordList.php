<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\record;

/**
 * A list class, for collecting `Record` instances.
 *
 * @package froq\database\record
 * @class   froq\database\record\RecordList
 * @author  Kerem Güneş
 * @since   5.0
 */
class RecordList extends \ItemList implements RecordListInterface
{
    /**
     * Constructor.
     *
     * @param array<froq\database\record\Record> $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items, type: Record::class);
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
