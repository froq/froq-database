<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\common;

/**
 * Platform class provides some utilities by given driver name.
 *
 * @package froq\database\common
 * @class   froq\database\common\Platform
 * @author  Kerem Güneş
 * @since   7.0
 */
class Platform
{
    /** Platform name. */
    private string $name;

    /**
     * Constructor.
     *
     * @param string $name
     * @param Exception
     */
    public function __construct(string $name)
    {
        if (!ctype_alpha($name)) {
            throw new \Exception(format(
                'Invalid platform name: %q, must be alphabetic name', $name
            ));
        }

        $this->name = strtolower($name);
    }

    /**
     * Check whether this name equals given name or names.
     *
     * @param  string    $name
     * @param  string ...$names
     * @return bool
     */
    public function equals(string $name, string ...$names): bool
    {
        return equals($this->name, $name, ...$names);
    }

    /**
     * Quote a name.
     *
     * @param  string $input
     * @return string
     */
    public function quoteName(string $input): string
    {
        return match ($this->name) {
            'mysql' => '`' . trim($input, '`')  . '`',
            'mssql' => '[' . trim($input, '[]') . ']',
            default => '"' . trim($input, '"')  . '"'
        };
    }

    /**
     * Escape a name.
     *
     * @param  string $input
     * @return string
     */
    public function escapeName(string $input): string
    {
        return match ($this->name) {
            'mysql' => str_replace('`', '``', $input),
            'mssql' => str_replace(']', ']]', $input),
            default => str_replace('"', '""', $input)
        };
    }

    /**
     * Get JSON function for select oparations (available for only PgSQL & MySQL).
     *
     * @param  bool $array
     * @return string|null
     */
    public function getJsonFunction(bool $array): string|null
    {
        return match ($this->name) {
            // @tome: "jsonb_" stuff changes given field order.
            'pgsql' => $array ? 'json_build_array' : 'json_build_object',
            'mysql' => $array ? 'json_array'       : 'json_object',
            default => null
        };
    }

    /**
     * Get random function.
     *
     * @return string
     */
    public function getRandomFunction(): string
    {
        return ($this->name === 'pgsql') ? 'random()' : 'rand()';
    }

    /**
     * Format for case insensitive like.
     *
     * @param  string $field
     * @param  string $search
     * @return string
     */
    public function formatILike(string $field, string $search): string
    {
        return match ($this->name) {
            'pgsql' => sprintf('%s ILIKE %s', $field, $search),
            default => sprintf('lower(%s) LIKE lower(%s)', $field, $search)
        };
    }

    /**
     * Format for case insensitive not like.
     *
     * @param  string $field
     * @param  string $search
     * @return string
     */
    public function formatNotILike(string $field, string $search): string
    {
        return match ($this->name) {
            'pgsql' => sprintf('%s NOT ILIKE %s', $field, $search),
            default => sprintf('lower(%s) NOT LIKE lower(%s)', $field, $search)
        };
    }
}
