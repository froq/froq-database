<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\common;

/**
 * Table wrapper, holds a table name & primary related stuff.
 *
 * @package froq\database\common
 * @class   froq\database\common\Table
 * @author  Kerem Güneş
 * @since   6.0
 */
class Table
{
    /** Table name. */
    protected string $name;

    /** Table primary name. */
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
     * @return string|null
     */
    public function getName(): string|null
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
     * @return string|null
     */
    public function getPrimary(): string|null
    {
        return $this->primary ?? null;
    }

    /**
     * Pack name & primary if set.
     *
     * @return array
     */
    public function pack(): array
    {
        $ret = [null, null];

        if ($name = $this->getName()) {
            $ret[0] = $name;
            if ($primary = $this->getPrimary()) {
                $ret[1] = $name . '.' . $primary;
            }
        }

        return $ret;
    }
}
