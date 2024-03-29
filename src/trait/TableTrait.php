<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\trait;

use froq\database\common\Table;

/**
 * A trait, provides table related stuff.
 *
 * @package froq\database\trait
 * @class   froq\database\trait\TableTrait
 * @author  Kerem Güneş
 * @since   5.0, 6.0
 */
trait TableTrait
{
    /** Table instance. */
    protected Table $table;

    /**
     * Set table.
     *
     * @param  froq\database\common\Table
     * @return self
     */
    public function setTable(Table $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Get table.
     *
     * @return froq\database\common\Table|null
     */
    public function getTable(): Table|null
    {
        return $this->table ?? null;
    }
}
