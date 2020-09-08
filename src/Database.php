<?php
/**
 * MIT License <https://opensource.org/licenses/mit>
 *
 * Copyright (c) 2015 Kerem Güneş
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\{DatabaseException, DatabaseConnectionException, DatabaseQueryException,
    Link, LinkException, Result, Profiler, Query};
use froq\database\sql\{Sql, Name, Date, DateTime};
use froq\pager\Pager;
use PDO, PDOStatement, PDOException, Exception;

/**
 * Database.
 * @package froq\database
 * @object  froq\database\Database
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   1.0, 4.0 Refactored.
 */
final class Database
{
    /**
     * Link.
     * @var froq\database\Link
     */
    private Link $link;

    /**
     * Profiler.
     * @var froq\database\Profiler.
     */
    private Profiler $profiler;

    /**
     * Constructor.
     * @param  array $options
     * @throws froq\database\DatabaseConnectionException
     */
    public function __construct(array $options)
    {
        // Default is false (no profiling).
        $profile = $options['profile'] ?? false;
        if ($profile) {
            $this->profiler = new Profiler();
        }

        $this->link = Link::init($options);
        try {
            empty($this->profiler) ? $this->link->connect()
                : $this->profiler->profileConnection(fn() => $this->link->connect());
        } catch (LinkException $e) {
            throw new DatabaseConnectionException($e->getMessage());
        }
    }

    /**
     * Get link.
     * @return froq\database\Link
     */
    public function getLink(): Link
    {
        return $this->link;
    }

    /**
     * Get profiler.
     * @return froq\database\Profiler
     * @throws froq\database\DatabaseException
     */
    public function getProfiler(): Profiler
    {
        if (empty($this->profiler)) {
            throw new DatabaseException('Database object has no profiler, be sure "profile" '.
                'option is "true" in configuration');
        }

        return $this->profiler;
    }

    /**
     * Query.
     * @param  string                    $query
     * @param  array|null                $queryParams
     * @param  string|array<string>|null $fetchOptions
     * @return froq\database\Result
     * @throws froq\database\DatabaseException, froq\database\DatabaseQueryException
     */
    public function query(string $query, array $queryParams = null, $fetchOptions = null): Result
    {
        $query = $queryParams ? $this->prepare($query, $queryParams) : trim($query);
        if ($query == '') {
            throw new DatabaseException('Empty query given to "%s()", non-empty query required',
                [__method__]);
        }

        try {
            $pdo = $this->link->getPdo();
            $pdoStatement = empty($this->profiler) ? $pdo->query($query)
                                : $this->profiler->profileQuery($query, fn() => $pdo->query($query));
            return new Result($pdo, $pdoStatement, $fetchOptions);
        } catch (PDOException $e) {
            throw new DatabaseQueryException($e->getMessage());
        }
    }

    /**
     * Execute.
     * @param  string     $query
     * @param  array|null $queryParams
     * @return ?int
     * @since  4.3
     */
    public function execute(string $query, array $queryParams = null): ?int
    {
        $query = $queryParams ? $this->prepare($query, $queryParams) : trim($query);
        if ($query == '') {
            throw new DatabaseException('Empty query given to "%s()", non-empty query required',
                [__method__]);
        }

        try {
            $pdo = $this->link->getPdo();
            $pdoResult = empty($this->profiler) ? $pdo->exec($query)
                             : $this->profiler->profileQuery($query, fn() => $pdo->exec($query));
            return ($pdoResult !== false) ? $pdoResult : null;
        } catch (PDOException $e) {
            throw new DatabaseQueryException($e->getMessage());
        }
    }

    /**
     * Get.
     * @param  string                    $query
     * @param  array|null                $queryParams
     * @param  string|array<string>|null $fetchOptions
     * @return ?array|?object
     */
    public function get(string $query, array $queryParams = null, $fetchOptions = null)
    {
        return $this->query($query, $queryParams, $fetchOptions)->row(0);
    }

    /**
     * Get all.
     * @param  string                    $query
     * @param  array|null                $queryParams
     * @param  string|array<string>|null $fetchOptions
     * @return ?array
     */
    public function getAll(string $query, array $queryParams = null, $fetchOptions = null): ?array
    {
        return $this->query($query, $queryParams, $fetchOptions)->rows();
    }

    /**
     * Select.
     * @param  string                    $table
     * @param  string                    $fields
     * @param  string|null               $where
     * @param  array|null                $whereParams
     * @param  string|null               $order
     * @param  string|array<string>|null $fetchOptions
     * @return ?array|?object
     */
    public function select(string $table, string $fields, string $where = null, array $whereParams = null,
        string $order = null, $fetchOptions = null)
    {
        $query = $this->initQuery($table)->select($fields);
        $where && $query->where($where, $whereParams);
        $order && $query->orderBy($order);
        $query->limit(1);

        return $query->run($fetchOptions)->row(0);
    }

    /**
     * Select all.
     * @param  string                    $table
     * @param  string                    $fields
     * @param  string|null               $where
     * @param  array|null                $whereParams
     * @param  string|null               $order
     * @param  int|array<int>|null       $limit
     * @param  string|array<string>|null $fetchOptions
     * @return ?array
     */
    public function selectAll(string $table, string $fields, string $where = null, array $whereParams = null,
        string $order = null, $limit = null, $fetchOptions = null): ?array
    {
        $query = $this->initQuery($table)->select($fields);
        $where && $query->where($where, $whereParams);
        $order && $query->orderBy($order);
        $limit && $query->limit(...(array) $limit);

        return $query->run($fetchOptions)->rows();
    }

    /**
     * Insert.
     * @param  string $table
     * @param  array  $data
     * @param  bool   $multi
     * @return ?int|?array<int>
     */
    public function insert(string $table, array $data, bool $multi = false)
    {
        $query = $this->initQuery($table)->insert($data, $multi);

        return !$multi ? $query->run()->id() : $query->run()->ids();
    }

    /**
     * Update.
     * @param  string      $table
     * @param  array       $data
     * @param  string|null $where
     * @param  array|null  $whereParams
     * @param  int|null    $limit
     * @return int
     */
    public function update(string $table, array $data, string $where = null, array $whereParams = null,
        int $limit = null): int
    {
        $query = $this->initQuery($table)->update($data);
        $where && $query->where($where, $whereParams);
        $limit && $query->limit($limit);

        return $query->run()->count();
    }

    /**
     * Delete.
     * @param  string      $table
     * @param  string|null $where
     * @param  array|null  $whereParams
     * @param  int|null    $limit
     * @return int
     */
    public function delete(string $table, string $where = null, array $whereParams = null,
        int $limit = null): int
    {
        $query = $this->initQuery($table)->delete();
        $where && $query->where($where, $whereParams);
        $limit && $query->limit($limit);

        return $query->run()->count();
    }

    /**
     * Count.
     * @param  string      $table
     * @param  string|null $where
     * @param  array|null  $whereParams
     * @return int
     */
    public function count(string $table, string $where = null, array $whereParams = null): int
    {
        $query = $this->initQuery($table);
        $where && $query->where($where, $whereParams);

        return $query->count();
    }

    /**
     * Count query.
     * @param  string     $query
     * @param  array|null $queryParams
     * @return int
     */
    public function countQuery(string $query, array $queryParams = null): int
    {
        $query  = 'SELECT count(*) AS c FROM ('. $query .') AS t';
        $result = $this->get($query, $queryParams, ['array']);

        return (int) ($result['c'] ?? 0);
    }

    /**
     * Transaction.
     * @param  callable $call
     * @return any
     * @throws froq\database\DatabaseException
     */
    public function transaction(callable $call)
    {
        $pdo = $this->link->getPdo();
        try {
            if (!$pdo->beginTransaction()) {
                throw new DatabaseException('Failed to start transaction');
            }
            // And for all others.
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage());
        }

        try {
            $ret = call_user_func($call, $this);
            $pdo->commit();
            return $ret;
        } catch(Exception $e) {
            $pdo->rollBack();
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Quote.
     * @param  string $input
     * @return string
     */
    public function quote(string $input): string
    {
        return '\'' . $input . '\'';
    }

    /**
     * Quote name.
     * @param  string $input
     * @return string
     */
    public function quoteName(string $input): string
    {
        if ($input == '*') {
            return $input;
        }

        if ($input && $input[0] == '@') {
            $input = substr($input, 1);
        }

        // Dot notations (eg: foo.id => "foo"."id").
        $pos = strpos($input, '.');
        if ($pos) {
            return $this->quoteName(substr($input, 0, $pos)) .'.'.
                   $this->quoteName(substr($input, $pos + 1));
        }

        $pdoDriver = $this->link->getPdoDriver();
        if ($pdoDriver == 'pgsql') {
            // Array notations.
            $pos = strpos($input, '[');
            if ($pos) {
                return $this->quoteName(substr($input, 0, $pos)) . substr($input, $pos);
            }
        }

        switch ($pdoDriver) {
            case 'mysql': return '`'. $input .'`';
            case 'mssql': return '['. $input .']';
                 default: return '"'. $input .'"';
        }
    }

    /**
     * Escape.
     * @param  any         $input
     * @param  string|null $inputFormat
     * @return any
     * @throws froq\database\DatabaseException
     */
    public function escape($input, string $inputFormat = null)
    {
        $inputType = gettype($input);

        if ($inputType == 'array' && $inputFormat != '?a') {
            return array_map(
                fn($input) => $this->escape($input, $inputFormat),
                $input
            );
        } elseif ($inputType == 'object') {
            $inputClass = get_class($input);
            switch ($inputClass) {
                case Sql::class:      return $input->content();
                case Name::class:     return $this->escapeName($input->content());
                case Date::class:
                case DateTime::class: return $this->escapeString($input->content());
                case Query::class:    return $input->toString();
                default:
                    throw new DatabaseException('Invalid input object "%s" given, valids are: '.
                        'Query, sql\{Sql, Name, Date, DateTime}', [$inputClass]);
            }
        }

        // Available placeholders are "?, ?? / ?s, ?i, ?f, ?b, ?n, ?r, ?a".
        if ($inputFormat) {
            if ($inputFormat == '?' || $inputFormat == '??') {
                return ($inputFormat == '?') ? $this->escape($input)
                                             : $this->escapeName($input);
            }

            switch ($inputFormat) {
                case '?s': return $this->escapeString((string) $input);
                case '?i': return (int) $input;
                case '?f': return (float) $input;
                case '?b': return $input ? 'true' : 'false';
                case '?r': return $input; // Raw.
                case '?n': return $this->escapeName($input);
                case '?a': return join(', ', (array) $this->escape($input)); // Array.
            }

            throw new DatabaseException('Unimplemented input format "%s" encountered',
                [$inputFormat]);
        }

        switch ($inputType) {
            case 'NULL':    return 'NULL';
            case 'string':  return $this->escapeString($input);
            case 'integer': return $input;
            case 'double':  return $input;
            case 'boolean': return $input ? 'true' : 'false';
            default:
                throw new DatabaseException('Unimplemented input type "%s" encountered',
                    [$inputType]);
        }
    }

    /**
     * Escape string.
     * @param  string $input
     * @param  bool   $quote
     * @param  string $extra
     * @return string
     */
    public function escapeString(string $input, bool $quote = true, string $extra = ''): string
    {
        $input = $this->link->getPdo()->quote($input);

        if (!$quote) {
            $input = trim($input, '\'');
        }
        if ($extra) {
            $input = addcslashes($input, $extra);
        }

        return $input;
    }

    /**
     * Escape like string.
     * @param  string $input
     * @param  bool   $quote
     * @return string
     */
    public function escapeLikeString(string $input, bool $quote = true): string
    {
        return $this->escapeString($input, $quote, '%_');
    }

    /**
     * Escape name.
     * @param  string $input
     * @return string
     */
    public function escapeName(string $input): string
    {
        switch ($this->link->getPdoDriver()) {
            case 'mysql': $input = str_replace('`', '``', $input); break;
            case 'mssql': $input = str_replace(']', ']]', $input); break;
                 default: $input = str_replace('"', '""', $input);
        }

        return $this->quoteName($input);
    }

    /**
     * Escape names.
     * @param  string $input
     * @return string
     */
    public function escapeNames(string $input): string
    {
        // Eg: "id, name ..." or "id as ID, ...".
        preg_match_all('~([^\s,]+)(?:\s+(?:(AS)\s+)?([^\s,]+))?~i', $input, $match);

        $names = array_filter($match[1], 'strlen');
        $aliases = array_filter($match[3], 'strlen');
        if (empty($names)) {
            return $input;
        }

        foreach ($names as $i => $name) {
            $names[$i] = $this->escapeName($name);
            if (isset($aliases[$i])) {
                $names[$i] .= ' AS '. $this->escapeName($aliases[$i]);
            }
        }

        return join(', ', $names);
    }

    /**
     * Prepare.
     * @param  string     $input
     * @param  array|null $inputParams
     * @return string
     * @throws froq\database\DatabaseException
     */
    public function prepare(string $input, array $inputParams = null): string
    {
        $input = $this->preparePrepareInput($input);
        if ($input == '') {
            throw new DatabaseException('Empty input given to "%s()", non-empty input required',
                [__method__]);
        }

        // Available placeholders are "?, ?? / ?s, ?i, ?f, ?b, ?n, ?r, ?a / :foo, :foo_bar".
        static $pattern = '~
              \?[sifbnra](?![\w]) # Scalars/(n)ame/(r)aw. Eg: ("id = ?i", ["1"]) or ("?n = ?i", ["id", "1"]).
            | \?\?                # Names (identifier).   Eg: ("?? = ?", ["id", 1]).
            | \?(?![\w&|])        # Any type.             Eg: ("id = ?", [1]), but not "id ?| array[..]" for PgSQL.
            | (?<!:):\w+          # Named parameters.     Eg: ("id = :id", [1]), but not "id::int" casts for PgSQL.
        ~xu';

        if (preg_match_all($pattern, $input, $match)) {
            if ($inputParams == null) {
                throw new DatabaseException('Empty input parameters given to "%s()", non-empty '.
                    'input parameters required when input contains parameter placeholders like '.
                    '"?", "??" or ":foo"', [__method__]);
            }

            $i = 0;
            $keys = $values = [];
            $holders = array_filter($match[0]);

            foreach ($holders as $holder) {
                $pos = strpos($holder, ':');
                if ($pos > -1) { // Named.
                    $key = trim($holder, ':');
                    if (!array_key_exists($key, $inputParams)) {
                        throw new DatabaseException('Replacement key "%s" not found in given '.
                            'parameters', [$key]);
                    }

                    $value = $this->escape($inputParams[$key]);
                    if (is_array($value)) {
                        $value = join(', ', $value);
                    }

                    $keys[] = '~:'. $key .'~';
                    $values[] = $value;
                } else { // Question-mark.
                    if (!array_key_exists($i, $inputParams)) {
                        throw new DatabaseException('Replacement index "%s" not found in given '.
                            'parameters', [$i]);
                    }

                    $value = $this->escape($inputParams[$i++], $holder);
                    if (is_array($value)) {
                        $value = join(', ', $value);
                    }

                    $keys[] = '~'. preg_quote($holder) .'(?![|&])~';
                    $values[] = $value;
                }
            }

            $input = preg_replace($keys, $values, $input, 1);
        }

        return $input;
    }

    /**
     * Prepare statement.
     * @param  string $input
     * @return PDOStatement
     * @throws froq\database\DatabaseException
     */
    public function prepareStatement(string $input): PDOStatement
    {
        $input = $this->preparePrepareInput($input);
        if ($input == '') {
            throw new DatabaseException('Empty input given to "%s()", non-empty input required',
                [__method__]);
        }

        try {
            return $this->link->getPdo()->prepare($input);
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }
    }

    /**
     * Prepare prepare input.
     * @param  string $input
     * @return string
     */
    public function preparePrepareInput(string $input): string
    {
        $input = trim($input);

        if ($input != '') {
            // Prepare names (eg: '@id = ?', 1 or '@[id,..]') .
            $pos = strpos($input, '@');
            if ($pos > -1) {
                $input = preg_replace_callback('~@([\w][\w\.\[\]]*)|@\[.+?\]~', function ($match) {
                    if (count($match) == 1) {
                        return $this->escapeNames(substr($match[0], 2, -1));
                    }
                    return $this->escapeName($match[1]);
                }, $input);
            }
        }

        return $input;
    }

    /**
     * Init pager.
     * @param  int|null $totalRecords
     * @param  int|null $limit
     * @return froq\pager\Pager
     */
    public final function initPager(int $totalRecords = null, int $limit = null): Pager
    {
        $pager = new Pager();
        $pager->run($totalRecords, $limit);

        return $pager;
    }

    /**
     * Init query.
     * @param  string|null $table
     * @return froq\database\Query
     */
    public function initQuery(string $table = null): Query
    {
        return new Query($this, $table);
    }
}
