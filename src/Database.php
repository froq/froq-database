<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\{DatabaseException, DatabaseLinkException, DatabaseQueryException,
    Link, LinkException, Result, Profiler, Query};
use froq\database\sql\{Sql, Name};
use froq\{pager\Pager, logger\Logger};
use PDO, PDOStatement, PDOException, Throwable;

/**
 * Database.
 *
 * Represents a database worker that contains some util methods for such operations CRUD'ing and
 * query preparing, querying/executing commands.
 *
 * @package froq\database
 * @object  froq\database\Database
 * @author  Kerem Güneş
 * @since   1.0, 4.0 Refactored.
 */
final class Database
{
    /** @var froq\database\Link */
    private Link $link;

    /** @var froq\logger\Logger|null */
    private Logger $logger;

    /** @var froq\database\Profiler|null. */
    private Profiler $profiler;

    /**
     * Constructor.
     *
     * Init a `Database` object initing a `Link` object and auto-connecting, or throw a
     * `DatabaseLinkException` if any connection error occurs.
     *
     * @param  array $options
     * @throws froq\database\DatabaseLinkException
     */
    public function __construct(array $options)
    {
        // Default is null (no logging).
        $logging = $options['logging'] ?? null;
        if ($logging) {
            $this->logger = new Logger($logging);
            $this->logger->slowQuery = $options['logging']['slowQuery'] ?? null; // Keep.
        }

        // Default is false (no profiling).
        $profiling = $options['profiling'] ?? false;
        if ($profiling) {
            $this->profiler = new Profiler();
        }

        $this->link = Link::init($options);
        try {
            !isset($this->profiler) ? $this->link->connect()
                : $this->profiler->profileConnection(fn() => $this->link->connect());
        } catch (LinkException $e) {
            throw new DatabaseLinkException($e);
        }
    }

    /**
     * Get link property.
     *
     * @return froq\database\Link
     */
    public function link(): Link
    {
        return $this->link;
    }

    /**
     * Get logger property or throw a `DatabaseException` if no logger.
     *
     * @return froq\logger\Logger
     * @throws froq\database\DatabaseException
     * @since  4.9
     */
    public function logger(): Logger
    {
        return isset($this->logger) ? $this->logger : throw new DatabaseException(
            'Database object has no logger, be sure `logging` field is not empty in options');
    }

    /**
     * Get profiler property or throw a `DatabaseException` if no profiler.
     *
     * @return froq\database\Profiler
     * @throws froq\database\DatabaseException
     */
    public function profiler(): Profiler
    {
        return isset($this->profiler) ? $this->profiler : throw new DatabaseException(
            'Database object has no profiler, be sure `profiling` field is not empty or false in options');
    }

    /**
     * Run an SQL query and returning its result as `Result` object, or throw a
     * `DatabaseQueryException` if any query error occurs.
     *
     * @param  string     $query
     * @param  array|null $params
     * @param  array|null $options
     * @return froq\database\Result
     * @throws froq\database\DatabaseException|DatabaseQueryException
     */
    public function query(string $query, array $params = null, array $options = null): Result
    {
        $query = $params ? $this->prepare($query, $params) : trim($query);
        $query || throw new DatabaseException('Empty query given to %s()', __method__);

        try {
            $slowQuery = isset($this->logger->slowQuery)
                && Profiler::mark('@slowQuery');

            $pdo          = $this->link->pdo();
            $pdoStatement = !isset($this->profiler) ? $pdo->query($query)
                : $this->profiler->profileQuery($query, fn() => $pdo->query($query));

            if ($slowQuery) {
                $time = Profiler::unmark('@slowQuery');
                if ($time >= $this->logger->slowQuery) {
                    $this->logger->logWarn('Slow query: time '. $time .', '. $query);
                }
            }

            return new Result($pdo, $pdoStatement, $options);
        } catch (PDOException $e) {
            throw new DatabaseQueryException($e);
        }
    }

    /**
     * Run an SQL query and returning its result as `int` or `null`, or throw a
     * `DatabaseQueryException` if any error occurs.
     *
     * @param  string     $query
     * @param  array|null $params
     * @return int|null
     * @throws froq\database\DatabaseException|DatabaseQueryException
     * @since  4.3
     */
    public function execute(string $query, array $params = null): int|null
    {
        $query = $params ? $this->prepare($query, $params) : trim($query);
        $query || throw new DatabaseException('Empty query given to %s()', __method__);

        try {
            $slowQuery = isset($this->logger->slowQuery)
                && Profiler::mark('@slowQuery');

            $pdo       = $this->link->pdo();
            $pdoResult = !isset($this->profiler) ? $pdo->exec($query)
                : $this->profiler->profileQuery($query, fn() => $pdo->exec($query));

            if ($slowQuery) {
                $time = Profiler::unmark('@slowQuery');
                if ($time >= $this->logger->slowQuery) {
                    $this->logger->logWarn('Slow query: time '. $time .', '. $query);
                }
            }

            return ($pdoResult !== false) ? $pdoResult : null;
        } catch (PDOException $e) {
            throw new DatabaseQueryException($e);
        }
    }

    /**
     * Get a single row running given query as `array|object` or return `null` if no match.
     *
     * @param  string            $query
     * @param  array|null        $params
     * @param  string|array|null $fetch
     * @return array|object|null
     */
    public function get(string $query, array $params = null, string|array $fetch = null): array|object|null
    {
        return $this->query($query, $params, ['fetch' => $fetch])->row(0);
    }

    /**
     * Get all rows running given query as `array` or return `null` if no matches.
     *
     * @param  string            $query
     * @param  array|null        $params
     * @param  string|array|null $fetch
     * @return array|null
     */
    public function getAll(string $query, array $params = null, string|array $fetch = null): array|null
    {
        return $this->query($query, $params, ['fetch' => $fetch])->rows();
    }

    /**
     * Select a row from given table as `array|object` or return `null` if no match.
     *
     * @param  string            $table
     * @param  string            $fields
     * @param  string|array|null $where
     * @param  array|null        $params
     * @param  string|null       $order
     * @param  string|array|null $fetch
     * @return array|object|null
     */
    public function select(string $table, string $fields = '*', string|array $where = null, array $params = null,
        string $order = null, string|array $fetch = null): array|object|null
    {
        $query = $this->initQuery($table)->select($fields);

        $where && $query->where(...$this->prepareWhereInput($where, $params));
        $order && $query->orderBy($order);
        $query->limit(1);

        return $query->run($fetch)->row(0);
    }

    /**
     * Select all rows from given table as `array` or return `null` if no matches.
     *
     * @param  string            $table
     * @param  string            $fields
     * @param  string|array|null $where
     * @param  array|null        $params
     * @param  string|null       $order
     * @param  int|array|null    $limit
     * @param  string|array|null $fetch
     * @return array|null
     */
    public function selectAll(string $table, string $fields = '*', string|array $where = null, array $params = null,
        string $order = null, int|array $limit = null, string|array $fetch = null): array|null
    {
        $query = $this->initQuery($table)->select($fields);

        $where && $query->where(...$this->prepareWhereInput($where, $params));
        $order && $query->orderBy($order);
        $limit && $query->limit(...(array) $limit);

        return $query->run($fetch)->rows();
    }

    /**
     * Insert row/rows to given table.
     *
     * @param  string $table
     * @param  array  $data
     * @param  array  $options
     * @return int|string|array|object|null
     */
    public function insert(string $table, array $data, array $options = null)
    {
        $return = $fetch = $batch = $conflict = $sequence = null;
        if ($options != null) {
            [$return, $fetch, $batch, $conflict, $sequence] = array_select(
                $options, ['return', 'fetch', 'batch', 'conflict', 'sequence']
            );
        }

        $query = $this->initQuery($table)->insert($data, $batch, $sequence);

        if ($conflict) {
            $conflict = (array) $conflict;

            if (isset($conflict['fields']) || isset($conflict['action'])) {
                [$fields, $action] = array_select($conflict, ['fields', 'action']);
            } else {
                [$fields, $action, $update, $where] = array_select($conflict, [0, 1, 2, 3]);
            }

            // Eg: 'conflict' => ['id', 'action' => 'nothing'] or ['id', 'update' => [..], 'where' => [..]].
            $fields ??= $conflict[0]        ?? null;
            $update ??= $conflict['update'] ?? null;
            $where  ??= $conflict['where']  ?? null;

            // Can be skipped with update.
            $update && $action = 'update';

            if (!$action) {
                throw new DatabaseException('Conflict action is not given');
            }
            if (!$update && strtolower($action) == 'update') {
                throw new DatabaseException('Conflict action is update, but no update data given');
            }

            $query->conflict($fields, $action, $update, $where);
        }

        $return && $query->return($return, $fetch);

        $result = $query->run();

        // If rows wanted as return.
        if ($return) {
            if ($batch) {
                $result = $result->rows();
            } else {
                // If single row wanted as return.
                $result = $result->row(0);
                if (!is_array($return)) {
                    $resultArray = (array) $result;
                    if (isset($resultArray[$return])) {
                        $result = $resultArray[$return];
                    }
                }
            }

            return $result;
        }

        return $batch ? $result->ids() : $result->id();
    }

    /**
     * Update row/rows on given table.
     *
     * @param  string            $table
     * @param  array             $data
     * @param  string|array|null $where
     * @param  array|null        $params
     * @param  array|null        $options
     * @return int|string|array|object|null
     */
    public function update(string $table, array $data, string|array $where = null, array $params = null, array $options = null)
    {
        $return = $fetch = $batch = $limit = null;
        if ($options != null) {
            [$return, $fetch, $batch, $limit] = array_select(
                $options, ['return', 'fetch', 'batch', 'limit']
            );
        }

        $query = $this->initQuery($table)->update($data);

        $where  && $query->where(...$this->prepareWhereInput($where, $params));
        $return && $query->return($return, $fetch);
        $limit  && $query->limit($limit);

        $result = $query->run();

        // If rows wanted as return.
        if ($return) {
            if ($batch) {
                $result = $result->rows();
            } else {
                // If single row wanted as return.
                $result = $result->row(0);
                if (!is_array($return)) {
                    $resultArray = (array) $result;
                    if (isset($resultArray[$return])) {
                        $result = $resultArray[$return];
                    }
                }
            }

            return $result;
        }

        return $result->count();
    }

    /**
     * Delete row/rows from given table.
     *
     * @param  string            $table
     * @param  string|array|null $where
     * @param  array|null        $params
     * @param  array|null        $options
     * @return int|string|array|object|null
     */
    public function delete(string $table, string|array $where = null, array $params = null, array $options = null)
    {
        $return = $fetch = $batch = $limit = null;
        if ($options != null) {
            [$return, $fetch, $batch, $limit] = array_select(
                $options, ['return', 'fetch', 'batch', 'limit']
            );
        }

        $query = $this->initQuery($table)->delete();

        $where  && $query->where(...$this->prepareWhereInput($where, $params));
        $return && $query->return($return, $fetch);
        $limit  && $query->limit($limit);

        $result = $query->run();

        // If rows wanted as return.
        if ($return) {
            if ($batch) {
                $result = $result->rows();
            } else {
                // If single row wanted as return.
                $result = $result->row(0);
                if (!is_array($return)) {
                    $resultArray = (array) $result;
                    if (isset($resultArray[$return])) {
                        $result = $resultArray[$return];
                    }
                }
            }

            return $result;
        }

        return $result->count();
    }

    /**
     * Count all rows on given table with/without given conditions.
     *
     * @param  string            $table
     * @param  string|array|null $where
     * @param  array|null        $params
     * @return int
     */
    public function count(string $table, string|array $where = null, array $params = null): int
    {
        $query = $this->initQuery($table);

        $where && $query->where(...$this->prepareWhereInput($where, $params));

        return $query->count();
    }

    /**
     * Run a count query with/without given conditions.
     *
     * @param  string     $query
     * @param  array|null $params
     * @return int
     */
    public function countQuery(string $query, array $params = null): int
    {
        $result = $this->get('SELECT count(*) AS c FROM ('. $query .') AS t', $params, 'array');

        return (int) ($result['c'] ?? 0);
    }

    /**
     * Increase given field/fields value, optionally returning current value or affected rows count.
     *
     * @param  string     $table
     * @param  array      $field
     * @param  float|int  $value
     * @param  array|null $where
     * @param  array|null $params
     * @param  bool       $return
     * @param  int|null   $limit
     * @return int|float|array|null
     */
    public function increase(string $table, string|array $field, int|float $value = 1, string|array $where = null,
        array $params = null, array $options = null)
    {
        $return = $fetch = $batch = $limit = null;
        if ($options != null) {
            [$return, $fetch, $batch, $limit] = array_select(
                $options, ['return', 'fetch', 'batch', 'limit']
            );
        }

        $query = $this->initQuery($table)->increase($field, $value, $return);

        $where && $query->where(...$this->prepareWhereInput($where, $params));
        $limit && $query->limit($limit);

        $result = $query->run();

        // If rows wanted as return.
        if ($return) {
            if ($batch) {
                $result = $result->rows();
            } else {
                // If single row wanted as return.
                $result      = $result->row(0);
                $resultArray = (array) $result;
                if (is_string($field)) {
                    $result = $resultArray[$field];
                } else {
                    $fields = array_keys($field);
                    $result = array_combine($fields, array_select($resultArray, $fields));
                }
            }

            return $result;
        }

        return $result->count();
    }

    /**
     * Decrease given field/fields value, optionally returning current value or affected rows count.
     *
     * @param  string     $table
     * @param  array      $field
     * @param  float|int  $value
     * @param  array|null $where
     * @param  array|null $params
     * @param  bool       $return
     * @param  int|null   $limit
     * @return int|float|array|null
     */
    public function decrease(string $table, string|array $field, int|float $value = 1, string|array $where = null,
        array $params = null, array $options = null)
    {
        $return = $fetch = $batch = $limit = null;
        if ($options != null) {
            [$return, $fetch, $batch, $limit] = array_select(
                $options, ['return', 'fetch', 'batch', 'limit']
            );
        }

        $query = $this->initQuery($table)->decrease($field, $value, $return);

        $where && $query->where(...$this->prepareWhereInput($where, $params));
        $limit && $query->limit($limit);

        $result = $query->run();

        // If rows wanted as return.
        if ($return) {
            if ($batch) {
                $result = $result->rows();
            } else {
                // If single row wanted as return.
                $result      = $result->row(0);
                $resultArray = (array) $result;
                if (is_string($field)) {
                    $result = $resultArray[$field];
                } else {
                    $fields = array_keys($field);
                    $result = array_combine($fields, array_select($resultArray, $fields));
                }
            }

            return $result;
        }

        return $result->count();
    }

    /**
     * Run a transaction in a try/catch block or return a `Transaction` object.
     *
     * @param  callable|null $call
     * @param  callable|null $callError
     * @return any
     * @throws Throwable
     */
    public function transaction(callable $call = null, callable $callError = null)
    {
        $transaction = new Transaction($this->link->pdo());

        // Return transaction object.
        if ($call == null) {
            return $transaction;
        }

        try {
            $transaction->begin();
            $ret = $call($this);
            $transaction->commit();

            return $ret;
        } catch (Throwable $e) {
            $transaction->rollback();

            // Block throw.
            if ($callError != null) {
                return $callError($e, $this);
            }

            throw $e;
        }
    }

    /**
     * Quote an input.
     *
     * @param  string $in
     * @return string
     */
    public function quote(string $in): string
    {
        return "'" . $in . "'";
    }

    /**
     * Quote a name input.
     *
     * @param  string $in
     * @return string
     */
    public function quoteName(string $in): string
    {
        if ($in == '*') {
            return $in;
        }

        if ($in && $in[0] == '@') {
            $in = substr($in, 1);
        }

        // For row(..) or other parenthesis stuff.
        if (strpos($in, '(') === 0) {
            $rpos = strpos($in, ')'); // Not parsed array[(foo, ..)] stuff, sorry.
            $rpos || throw new DatabaseException('Unclosed parenthesis in `%s` input', $in);

            $name = substr($in, 1, $rpos - 1); // Eg: part foo of (foo).
            $rest = substr($in, $rpos + 1) ?: ''; // Eg: part ::int of (foo)::int.

            return '(' . $this->quoteNames($name) . ')' . $rest;
        }

        // Dot notations (eg: foo.id => "foo"."id").
        $pos = strpos($in, '.');
        if ($pos) {
            return $this->quoteNames(substr($in, 0, $pos)) . '.' .
                   $this->quoteNames(substr($in, $pos + 1));
        }

        $driver = $this->link->driver();
        if ($driver == 'pgsql') {
            // Cast notations (eg: foo::int).
            if ($pos = strpos($in, '::')) {
                return $this->quoteName(substr($in, 0, $pos)) . substr($in, $pos);
            }

            // Array notations (eg: foo[], foo[1] or array[foo, bar]).
            if ($pos = strpos($in, '[')) {
                $name = substr($in, 0, $pos);
                $rest = substr($in, $pos + 1);
                $last = '';
                if (strpos($rest, ']')) {
                    $rest = substr($in, $pos + 1, -1);
                    $last = ']';
                }

                return (strtolower($name) == 'array')
                     ? $name . '[' . $this->quoteNames($rest) . $last
                     : $this->quoteName($name) . '[' . $rest . $last;
            }
        }

        return match ($driver) {
            'mysql' => '`' . trim($in, '`')  . '`',
            'mssql' => '[' . trim($in, '[]') . ']',
            default => '"' . trim($in, '"')  . '"'
        };
    }

    /**
     * Quote names in given input.
     *
     * @param  string $in
     * @return string
     * @since  4.14
     */
    public function quoteNames(string $in): string
    {
        // Eg: "id, name ..." or "id as ID, ...".
        preg_match_all('~([^\s,]+)~i', $in, $match);

        $names = array_filter($match[1], 'strlen');
        if (!$names) {
            return $in;
        }

        foreach ($names as $i => $name) {
            $names[$i] = $this->quoteName($name);
        }

        return join(', ', $names);
    }

    /**
     * Escape an input with/without given format.
     *
     * @param  any         $in
     * @param  string|null $format
     * @return any
     * @throws froq\database\DatabaseException
     */
    public function escape($in, string $format = null)
    {
        if (is_array($in) && $format != '?a') {
            return array_map(fn($in) => $this->escape($in, $format), $in);
        }
        if (is_object($in)) {
            return match (true) {
                ($in instanceof Sql)   => $in->content(),
                ($in instanceof Name)  => $this->escapeName($in->content()),
                ($in instanceof Query) => $in->toString(),
                default                => throw new DatabaseException('Invalid input object `%s`,'
                    . ' valids are: Query, sql\{Sql, Name}', $in::class)
            };
        }

        // Available placeholders: ?, ??, ?s, ?i, ?f, ?b, ?r, ?n, ?a.
        if ($format != null) {
            return match ($format) {
                '?'     => $this->escape($in),
                '??'    => $this->escapeName($in),
                '?s'    => $this->escapeString((string) $in),
                '?i'    => (int) $in,
                '?f'    => (float) $in,
                '?b'    => $in ? 'true' : 'false',
                '?r'    => $in, // Raw input.
                '?n'    => $this->escapeName($in),
                '?a'    => join(', ', (array) $this->escape($in)), // Array input.
                default => throw new DatabaseException('Unimplemented input format `%s`', $format)
            };
        }

        $type = get_type($in);

        return match ($type) {
            'null'         => 'NULL',
            'string'       => $this->escapeString($in),
            'int', 'float' => $in,
            'bool'         => $in ? 'true' : 'false',
            default        => throw new DatabaseException('Unimplemented input type `%s`', $type)
        };
    }

    /**
     * Escape a string input.
     *
     * @param  string $in
     * @param  bool   $quote
     * @param  string $extra
     * @return string
     */
    public function escapeString(string $in, bool $quote = true, string $extra = ''): string
    {
        $out = $this->link->pdo()->quote($in);

        $quote || $out = trim($out, "'");
        $extra && $out = addcslashes($out, $extra);

        return $out;
    }

    /**
     * Escape like string input.
     *
     * @param  string $in
     * @param  bool   $quote
     * @return string
     */
    public function escapeLikeString(string $in, bool $quote = true): string
    {
        return $this->escapeString($in, $quote, '%_');
    }

    /**
     * Escape a name input.
     *
     * @param  string $in
     * @return string
     */
    public function escapeName(string $in): string
    {
        $in = match ($this->link->driver()) {
            'mysql' => str_replace('`', '``', $in),
            'mssql' => str_replace(']', ']]', $in),
            default => str_replace('"', '""', $in)
        };

        return $this->quoteName($in);
    }

    /**
     * Escape names in given input.
     *
     * @param  string $in
     * @return string
     */
    public function escapeNames(string $in): string
    {
        // Eg: "id, name ..." or "id as ID, ...".
        preg_match_all('~([^\s,]+)(?:\s+(?:(AS)\s+)?([^\s,]+))?~i', $in, $match);

        $names = array_filter($match[1], 'strlen');
        if (!$names) {
            return $in;
        }

        $aliases = array_filter($match[3], 'strlen');
        foreach ($names as $i => $name) {
            $names[$i] = $this->escapeName($name);
            if (isset($aliases[$i])) {
                $names[$i] .= ' AS '. $this->escapeName($aliases[$i]);
            }
        }

        return join(', ', $names);
    }

    /**
     * Prepare given input returning a string output.
     *
     * @param  string     $in
     * @param  array|null $params
     * @return string
     * @throws froq\database\DatabaseException
     */
    public function prepare(string $in, array $params = null): string
    {
        $out = $this->prepareNameInput($in);
        $out || throw new DatabaseException('Empty input given to %s() for preparation', __method__);

        // Available placeholders are "?, ?? / ?s, ?i, ?f, ?b, ?n, ?r, ?a / :foo, :foo_bar".
        static $pattern = '~
              \?[sifbnra](?![\w]) # Scalars/(n)ame/(r)aw. Eg: ("id = ?i", ["1"]) or ("?n = ?i", ["id", "1"]).
            | \?\?                # Names (identifier).   Eg: ("?? = ?", ["id", 1]).
            | \?(?![\w&|])        # Any type.             Eg: ("id = ?", [1]), but not "id ?| array[..]" for PgSQL.
            | (?<!:):\w+          # Named parameters.     Eg: ("id = :id", [1]), but not "id::int" casts for PgSQL.
        ~xu';

        if (preg_match_all($pattern, $out, $match)) {
            if ($params == null) {
                throw new DatabaseException('Empty input parameters given to %s(), non-empty input parameters'
                    . ' required when input contains parameter placeholders like ?, ?? or :foo', __method__);
            }

            $i       = 0;
            $keys    = $values = [];
            $holders = array_filter($match[0]);

            foreach ($holders as $holder) {
                $pos = strpos($holder, ':');
                if ($pos > -1) { // Named.
                    $key = trim($holder, ':');
                    if (!array_key_exists($key, $params)) {
                        throw new DatabaseException('Replacement key `%s` not found in given parameters', $key);
                    }

                    $value = $this->escape($params[$key]);
                    if (is_array($value)) {
                        $value = join(', ', $value);
                    }

                    $keys[]   = '~:' . $key . '~';
                    $values[] = $value;
                } else { // Question-mark.
                    if (!array_key_exists($i, $params)) {
                        throw new DatabaseException('Replacement index `%s` not found in given parameters', $i);
                    }

                    $value = $this->escape($params[$i++], $holder);
                    if (is_array($value)) {
                        $value = join(', ', $value);
                    }

                    $keys[]   = '~' . preg_quote($holder) . '(?![|&])~'; // PgSQL operators.
                    $values[] = $value;
                }
            }

            $out = preg_replace($keys, $values, $out, 1);
        }

        return $out;
    }

    /**
     * Prepare given input returning a string output with escaped names.
     *
     * @param  string $in
     * @return string
     * @since  5.0
     */
    public function prepareName(string $in): string
    {
        $out = $this->prepareNameInput($in);
        $out || throw new DatabaseException('Empty input given to %s() for preparation', __method__);

        return $out;
    }

    /**
     * Prepare statement input returning a `PDOStatement` object.
     *
     * @param  string $in
     * @return PDOStatement
     * @throws froq\database\DatabaseException
     */
    public function prepareStatement(string $in): PDOStatement
    {
        $out = $this->prepareNameInput($in);
        $out || throw new DatabaseException('Empty input given to %s() for preparation', __method__);

        try {
            return $this->link->pdo()->prepare($out);
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }
    }

    /**
     * Init an `Sql` object with/without given params.
     *
     * @param  string     $in
     * @param  array|null $params
     * @return froq\database\sql\Sql
     */
    public function initSql(string $in, array $params = null): Sql
    {
        $params && $in = $this->prepare($in, $params);

        return new Sql($in);
    }

    /**
     * Init a `Query` object.
     *
     * @param  string|null $table
     * @return froq\database\Query
     */
    public function initQuery(string $table = null): Query
    {
        return new Query($this, $table);
    }

    /**
     * Init a `Pager` object.
     *
     * @param  int|null $count
     * @param  int|null $limit
     * @return froq\pager\Pager
     */
    public function initPager(int $count = null, int $limit = null): Pager
    {
        $pager = new Pager();
        $pager->run($count, $limit);

        return $pager;
    }

    /**
     * Prepare a prepare input escaping names only eg. `@id`.
     *
     * @param  string $in
     * @return string
     */
    private function prepareNameInput(string $in): string
    {
        $out = trim($in);

        if ($out != '') {
            // Prepare names (eg: '@id = ?', 1 or '@[id, ..]').
            $pos = strpos($out, '@');
            if ($pos > -1) {
                $out = preg_replace_callback('~@([\w][\w\.\[\]]*)|@\[.+?\]~', function ($match) {
                    if (count($match) == 1) {
                        return $this->escapeNames(substr($match[0], 2, -1));
                    }
                    return $this->escapeName($match[1]);
                }, $out);
            }
        }

        return $out;
    }

    /**
     * Prepare a where input.
     *
     * @param  string|array $in
     * @param  array|null   $params
     * @return array
     * @since  4.15
     * @throws froq\database\DatabaseException
     */
    private function prepareWhereInput(string|array $in, array $params = null): array
    {
        $where = $in;

        if ($where != null) {
            if (is_string($where)) {
                $where  = trim($where);
                $params = (array) $params;
            } else {
                static $signs = ['!', '<', '>'];
                // Note: "where" must not be combined when array given, eg: (["a = ? AND b = ?" => [1, 2]])
                // will not be prepared and prepare() method will throw exception about replacement index. So
                // use ("a = ? AND b = ?", [1, 2]) convention instead for multiple conditions.
                $temp = [];
                foreach ($where as $field => $value) {
                    is_string($field) || throw new DatabaseException(
                        'Invalid where input, use ("a = ? AND b = ?", [1, 2]) convention'
                    );

                    $sign = ' = ';
                    if (in_array($field[-1], $signs)) {
                        $sign  = ' != ';
                        $field = substr($field, 0, -1);
                    }

                    ctype_alnum($field) || throw new DatabaseException(
                        'Invalid field name `%s` in where input, use an alphanumeric name', $field
                    );

                    // Add placeholders.
                    $field = $this->quoteName($field) . $sign . '?';

                    $temp[$field] = $value;
                }

                $where  = join(' AND ', array_keys($temp));
                $params = array_values($temp);
            }
        }

        return [$where, $params];
    }
}
