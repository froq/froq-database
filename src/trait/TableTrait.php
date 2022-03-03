<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\trait;

/**
 * Table Trait.
 *
 * A trait, provides table properties and related methods.
 *
 * @package froq\database\trait
 * @object  froq\database\trait\TableTrait
 * @author  Kerem Güneş
 * @since   5.0
 */
trait TableTrait
{
    /** @var string, string */
    protected string $table, $tablePrimary;

    /**
     * Set table.
     *
     * @param  string $table
     * @return self
     */
    public final function setTable(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Get table.
     *
     * @return string|null
     */
    public final function getTable(): string|null
    {
        return $this->table ?? null;
    }

    /**
     * Set table primary.
     *
     * @param  string $tablePrimary
     * @return self
     */
    public final function setTablePrimary(string $tablePrimary): self
    {
        $this->tablePrimary = $tablePrimary;

        return $this;
    }

    /**
     * Get table primary.
     *
     * @return string|null
     */
    public final function getTablePrimary(): string|null
    {
        return $this->tablePrimary ?? null;
    }
}
