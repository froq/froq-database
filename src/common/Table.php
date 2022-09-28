<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\common;

/**
 * Table wrapper, holds a table name & primary related stuff.
 *
 * @package froq\database\common
 * @object  froq\database\common\Table
 * @author  Kerem Güneş
 * @since   6.0
 */
class Table
{
    /** @var string */
    protected string $name;

    /** @var string */
    protected string $primary = 'id';

    /**
     * Constructor.
     *
     * @param string|null $name
     * @param string|null $primary
     */
    public function __construct(string $name = null, string $primary = null)
    {
        $name    && $this->name    = $name;
        $primary && $this->primary = $primary;
    }

    /**
     * Set name.
     *
     * @param  string
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name ?? null;
    }

    /**
     * Set primary.
     *
     * @param  string
     * @return self
     */
    public function setPrimary(string $primary): self
    {
        $this->primary = $primary;

        return $this;
    }

    /**
     * Get primary.
     *
     * @return ?string
     */
    public function getPrimary(): ?string
    {
        return $this->primary ?? null;
    }
}
