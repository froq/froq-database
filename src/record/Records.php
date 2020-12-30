<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\database\record\{RecordsException, Record};
use froq\collection\ItemCollection;
use froq\pager\Pager;

/**
 * Records.
 *
 * Represents a record set entity that used internally and holds `$pager` property and its getter method, and also
 * all basic collection methods such as `filter()`, `map(), `reduce()` etc.
 *
 * @package froq\database\record
 * @object  froq\database\record\Records
 * @author  Kerem Güneş
 * @since   5.0
 */
class Records extends ItemCollection
{
    /** @var ?froq\pager\Pager */
    protected ?Pager $pager;

    /**
     * Constructor.
     *
     * @param array                 $items
     * @param froq\pager\Pager|null $pager
     */
    public function __construct(array $items, Pager $pager = null)
    {
        foreach ($items as $item) {
            ($item instanceof Record) || throw new RecordsException(
                'Each item must extend class %s, %s given', [Record::class, typeof($item)]
            );
        }

        parent::__construct($items);

        // Set pager & lock.
        $this->pager = $pager;
        $this->readOnly(true);
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
}
