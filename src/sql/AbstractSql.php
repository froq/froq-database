<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\sql;

/**
 * Base class of `Sql` and `Name` classes.
 *
 * @package froq\database\sql
 * @class   froq\database\sql\AbstractSql
 * @author  Kerem Güneş
 * @since   4.0
 * @internal
 */
abstract class AbstractSql implements \Stringable
{
    /** Content. */
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
            throw new SqlException('Empty content given to %q object', static::class);
        }

        $this->content = $content;
    }

    /**
     * @magic
     */
    public function __toString(): string
    {
        return $this->content;
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
