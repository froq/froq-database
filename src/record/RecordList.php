<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\pager\Pager;

/**
 * A list class, used internally and holds `$pager` property and its getter method,
 * and also all basic list methods such as `filter()`, `map(), `reduce()` etc.
 *
 * @package froq\database\record
 * @object  froq\database\record\RecordList
 * @author  Kerem Güneş
 * @since   5.0
 */
class RecordList extends \ItemList implements RecordListInterface
{
    /** @var froq\pager\Pager|null */
    protected Pager|null $pager;

    /**
     * Constructor.
     *
     * @param array                 $items
     * @param froq\pager\Pager|null $pager
     * @param bool                  $locked
     */
    public function __construct(array $items = [], Pager $pager = null, bool $locked = false)
    {
        parent::__construct($items, type: $this->extractType(), locked: $locked);

        $this->pager = $pager;
    }

    /**
     * @override
     */
    public function __debugInfo(): array
    {
        return ['count' => $this->count()] + parent::__debugInfo();
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

    /**
     * @override
     */
    public final function toArray(bool $deep = true): array
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
