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
use froq\logger\Logger;
use PDO, PDOStatement, PDOException, Throwable;

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
     * Logger.
     * @var froq\logger\Logger|null
     * @since 4.9
     */
    private Logger $logger;

    /**
     * Profiler.
     * @var froq\database\Profiler|null.
     */
    private Profiler $profiler;

    /**
     * Constructor.
     * @param  array $options
     * @throws froq\database\DatabaseConnectionException
     */
    public function __construct(array $options)
    {
        $logging = $options['logging'] ?? null;
        if ($logging) {
            $this->logger = new Logger($logging);
        }

        // Default is false (no profiling).
        $profiling = $options['profiling'] ?? false;
        if ($profiling) {
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
     * Get logger.
     * @return froq\logger\Logger
     * @throws froq\database\DatabaseException
     * @since  4.9
     */
    public function getLogger(): Logger
    {
        if (empty($this->logger)) {
            throw new DatabaseException('Database object has no logger, be sure "logging" '.
                'field is not empty in options');
        }

        return $this->logger;
    }

    /**
     * Get profiler.
     * @return froq\database\Profiler
     * @throws froq\database\DatabaseException
     */
    public function getProfiler(): Profiler
    {
        if (empty($this->profiler)) {
            throw new DatabaseException('Database object has no profiler, be sure "profiling" '.
                'field is not empty or false in options');
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
     * @param  string|array|null         $where
     * @param  any|null                  $whereParams
     * @param  string|null               $order
     * @param  string|array<string>|null $fetchOptions
     * @return ?array|?object
     */
    public function select(string $table, string $fields = '*', $where = null, $whereParams = null,
        string $order = null, $fetchOptions = null)
    {
        $query = $this->initQuery($table)->select($fields);

        if ($where) {
            [$where, $whereParams] = $this->prepareWhereInput($where, $whereParams);
            $query->where($where, $whereParams);
        }

        $order && $query->orderBy($order);
        $query->limit(1);

        return $query->run($fetchOptions)->row(0);
    }

    /**
     * Select all.
     * @param  string                    $table
     * @param  string                    $fields
     * @param  string|array|null         $where
     * @param  any|null                  $whereParams
     * @param  string|null               $order
     * @param  int|array<int>|null       $limit
     * @param  string|array<string>|null $fetchOptions
     * @return ?array
     */
    public function selectAll(string $table, string $fields = '*', $where = null, $whereParams = null,
        string $order = null, $limit = null, $fetchOptions = null): ?array
    {
        $query = $this->initQuery($table)->select($fields);

        if ($where) {
            [$where, $whereParams] = $this->prepareWhereInput($where, $whereParams);
            $query->where($where, $whereParams);
        }

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
     * @param  string            $table
     * @param  array             $data
     * @param  string|array|null $where
     * @param  any|null          $whereParams
     * @param  int|null          $limit
     * @return int
     */
    public function update(string $table, array $data, $where = null, $whereParams = null,
        int $limit = null): int
    {
        $query = $this->initQuery($table)->update($data);

        if ($where) {
            [$where, $whereParams] = $this->prepareWhereInput($where, $whereParams);
            $query->where($where, $whereParams);
        }

        $limit && $query->limit($limit);

        return $query->run()->count();
    }

    /**
     * Delete.
     * @param  string            $table
     * @param  string|array|null $where
     * @param  array|null        $whereParams
     * @param  int|null          $limit
     * @return int
     */
    public function delete(string $table, $where = null, $whereParams = null, int $limit = null): int
    {
        $query = $this->initQuery($table)->delete();

        if ($where) {
            [$where, $whereParams] = $this->prepareWhereInput($where, $whereParams);
            $query->where($where, $whereParams);
        }

        $limit && $query->limit($limit);

        return $query->run()->count();
    }

    /**
     * Count.
     * @param  string            $table
     * @param  string|array|null $where
     * @param  array|null        $whereParams
     * @return int
     */
    public function count(string $table, $where = null, $whereParams = null): int
    {
        $query = $this->initQuery($table);

        if ($where) {
            [$where, $whereParams] = $this->prepareWhereInput($where, $whereParams);
            $query->where($where, $whereParams);
        }

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
        $result = $this->get('SELECT count(*) AS c FROM ('. $query .') AS t',
            $queryParams, 'array');

        return (int) ($result['c'] ?? 0);
    }

    /**
     * Transaction.
     * @param  callable $call
     * @param  callable $callError
     * @return any
     * @throws froq\database\DatabaseException If no call error.
     */
    public function transaction(callable $call, callable $callError = null)
    {
        $pdo = $this->link->getPdo();
        try {
            if (!$pdo->beginTransaction()) {
                throw new DatabaseException('Failed to start transaction');
            }
            // And for all others.
        } catch (Throwable $e) {
            throw new DatabaseException($e->getMessage());
        }

        try {
            $ret = $call($this);
            $pdo->commit();

            return $ret;
        } catch (Throwable $e) {
            $pdo->rollBack();

            // This will block exception below.
            if ($callError) {
                return $callError($e);
            }

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
        return '\''. $input .'\'';
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

        // For row(..) or other parenthesis stuff.
        if (strpos($input, '(') === 0) {
            $rpos = strpos($input, ')');
            if (!$rpos) { // Not parsed array[(foo, ..)] stuff, sorry.
                throw new DatabaseException('Unclosed parenthesis in "%s" input', [$input]);
            }

            $name = substr($input, 1, $rpos - 1); // Eg: part foo of (foo).
            $rest = substr($input, $rpos + 1) ?: ''; // Eg: part ::int of (foo)::int.

            return '('. $this->quoteNames($name) .')'. $rest;
        }

        // Dot notations (eg: foo.id => "foo"."id").
        $pos = strpos($input, '.');
        if ($pos) {
            return $this->quoteNames(substr($input, 0, $pos)) .'.'.
                   $this->quoteNames(substr($input, $pos + 1));
        }

        $pdoDriver = $this->link->getPdoDriver();
        if ($pdoDriver == 'pgsql') {
            // Cast notations (eg: foo::int).
            if ($pos = strpos($input, '::')) {
                return $this->quoteName(substr($input, 0, $pos)) . substr($input, $pos);
            }

            // Array notations (eg: foo[], foo[1] or array[foo, bar]).
            if ($pos = strpos($input, '[')) {
                $name = substr($input, 0, $pos);
                $rest = substr($input, $pos + 1);

                return (strtolower($name) == 'array')
                     ? $name .'['. $this->quoteNames($rest)
                     : $this->quoteName($name) .'['. $rest;
            }
        }

        switch ($pdoDriver) {
            case 'mysql': return '`'. $input .'`';
            case 'mssql': return '['. $input .']';
                 default: return '"'. $input .'"';
        }
    }

    /**
     * Quote names.
     * @param  string $input
     * @return string
     * @since  4.14
     */
    public function quoteNames(string $input): string
    {
        // Eg: "id, name ..." or "id as ID, ...".
        preg_match_all('~([^\s,]+)~i', $input, $match);

        $names = array_filter($match[1], 'strlen');
        if (!$names) {
            return $input;
        }

        foreach ($names as $i => $name) {
            $names[$i] = $this->quoteName($name);
        }

        return join(', ', $names);
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
        if (!$names) {
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
            throw new DatabaseException('Empty input given to "%s()" for preparation', [__method__]);
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
            throw new DatabaseException('Empty input given to "%s()" for preparation', [__method__]);
        }

        try {
            return $this->link->getPdo()->prepare($input);
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }
    }

    /**
     * Init pager.
     * @param  int|null $totalRecords
     * @param  int|null $limit
     * @return froq\pager\Pager
     */
    public function initPager(int $totalRecords = null, int $limit = null): Pager
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

    /**
     * Prepare where input.
     * @param  string|array $input
     * @param  any|null     $inputParams
     * @param  string       $op
     * @return array
     * @since  4.15
     * @throws froq\database\DatabaseException
     */
    private function prepareWhereInput($input, $inputParams = null, string $op = 'AND'): array
    {
        [$where, $whereParams] = [$input, $inputParams];

        if ($where != null) {
            // Note: "where" must not be combined when array given, eg: (["foo = ? AND bar = ?" => [1, 2]])
            // will not be prepared and prepare() method will throw exception about replacement index. So
            // use ("foo = ? AND bar = ?", [1, 2]) convention instead for multiple conditions.
            if (is_array($where)) {
                $temp = [];
                foreach ($where as $key => $value) {
                    // Check whether a placeholder given or not (eg: ["foo" => 1]).
                    if (strpbrk($key, '?:') === false) {
                        $key = $key .' = ?';
                    }
                    $temp[$key] = $value;
                }

                $where = join(($op ? ' '. $op .' ' : ''), array_keys($temp));
                $whereParams = array_values($temp);
            } elseif (is_string($where)) {
                $where = trim($where);
                $whereParams = (array) $whereParams;
            } else {
                throw new DatabaseException('Invalid $where input "%s", valids are: string, array',
                    gettype($where));
            }
        }

        return [$where, $whereParams];
    }

    /**
     * Prepare prepare input.
     * @param  string $input
     * @return string
     */
    private function preparePrepareInput(string $input): string
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
}
