<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\sql;

use froq\database\sql\SqlException;

/**
 * Abstract Sql.
 *
 * @package froq\database\sql
 * @object  froq\database\sql\AbstractSql
 * @author  Kerem Güneş
 * @since   4.0
 */
abstract class AbstractSql
{
    /** @var string */
    protected string $content;

    /**
     * Constructor.
     *
     * @param  string $content
     * @throws froq\database\sql\SqlException
     */
    public function __construct(string $content)
    {
        $content = trim($content);

        if ($content === '') {
            throw new SqlException('Empty content given to `%s` object', static::class);
        }

        $this->content = $content;
    }

    /**
     * Magic - string.
     */
    public function __toString()
    {
        return $this->content();
    }

    /**
     * Get content.
     *
     * @return string
     */
    public function content(): string
    {
        return $this->content;
    }
}
