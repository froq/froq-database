<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\{DatabaseException, DatabaseConnectionException, DatabaseQueryException,
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
 * @author  Kerem Güneş <k-gun@mail.com>
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
     * `DatabaseConnectionException` if any connection error occurs.
     *
     * @param  array $options
     * @throws froq\database\DatabaseConnectionException
     */
    public function __construct(array $options)
    {
        // Default is null (no logging).
        $logging = $options['logging'] ?? null;
        if ($logging) {
            $this->logger = new Logger($logging);
            $this->logger->slowQuery = $options['logging']['slowQuery'] ?? null;
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
            throw new DatabaseConnectionException($e);
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
        if (empty($this->logger)) {
            throw new DatabaseException("Database object has no logger, be sure 'logging' "
                . "field is not empty in options");
        }

        return $this->logger;
    }

    /**
     * Get profiler property or throw a `DatabaseException` if no profiler.
     *
     * @return froq\database\Profiler
     * @throws froq\database\DatabaseException
     */
    public function profiler(): Profiler
    {
        if (empty($this->profiler)) {
            throw new DatabaseException("Database object has no profiler, be sure 'profiling' "
                . "field is not empty or false in options");
        }

        return $this->profiler;
    }

    /**
     * Run an SQL query and returning its result as `Result` object, or throw a
     * `DatabaseQueryException` if any query error occurs.
     *
     * @param  string     $query
     * @param  array|null $queryParams
     * @param  array|null $options
     * @return froq\database\Result
     * @throws froq\database\DatabaseException|DatabaseQueryException
     */
    public function query(string $query, array $queryParams = null, array $options = null): Result
    {
        $query = $queryParams ? $this->prepare($query, $queryParams) : trim($query);
        if ($query == '') {
            throw new DatabaseException("Empty query given to '%s()'", __method__);
        }

        try {
            if (isset($this->logger->slowQuery)) {
                $this->logger->slowQueryTick = microtime(true);
            }

            $pdo = $this->link->pdo();
            $pdoStatement = empty($this->profiler) ? $pdo->query($query)
                : $this->profiler->profileQuery($query, fn() => $pdo->query($query));

            if (isset($this->logger->slowQuery)) {
                $time = microtime(true) - $this->logger->slowQueryTick;
                if ($time > $this->logger->slowQuery) {
                    $this->logger->logWarn(sprintf('Slow query: time %.6F, %s', $time, $query));
                }
                $this->logger->slowQueryTick = null;
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
     * @param  array|null $queryParams
     * @return ?int
     * @throws froq\database\DatabaseException|DatabaseQueryException
     * @since  4.3
     */
    public function execute(string $query, array $queryParams = null): ?int
    {
        $query = $queryParams ? $this->prepare($query, $queryParams) : trim($query);
        if ($query == '') {
            throw new DatabaseException("Empty query given to '%s()'", __method__);
        }

        try {
            $pdo = $this->link->pdo();
            $pdoResult = empty($this->profiler) ? $pdo->exec($query)
                : $this->profiler->profileQuery($query, fn() => $pdo->exec($query));

            return ($pdoResult !== false) ? $pdoResult : null;
        } catch (PDOException $e) {
            throw new DatabaseQueryException($e);
        }
    }

    /**
     * Get a single row running given query as `array|object` or return `null` if no match.
     *
     * @param  string                    $query
     * @param  array|null                $queryParams
     * @param  string|array<string>|null $fetch
     * @return ?array|?object
     */
    public function get(string $query, array $queryParams = null, $fetch = null)
    {
        return $this->query($query, $queryParams, ['fetch' => $fetch])->row(0);
    }

    /**
     * Get all rows running given query as `array` or return `null` if no matches.
     *
     * @param  string                    $query
     * @param  array|null                $queryParams
     * @param  string|array<string>|null $fetch
     * @return ?array
     */
    public function getAll(string $query, array $queryParams = null, $fetch = null): ?array
    {
        return $this->query($query, $queryParams, ['fetch' => $fetch])->rows();
    }

    /**
     * Select a row from given table as `array|object` or return `null` if no match.
     *
     * @param  string                    $table
     * @param  string                    $fields
     * @param  string|array|null         $where
     * @param  any|null                  $whereParams
     * @param  string|null               $order
     * @param  string|array<string>|null $fetch
     * @return ?array|?object
     */
    public function select(string $table, string $fields = '*', $where = null, $whereParams = null,
        string $order = null, $fetch = null)
    {
        $query = $this->initQuery($table)->select($fields);

        if ($where) {
            [$where, $whereParams] = $this->prepareWhereInput($where, $whereParams);
            $query->where($where, $whereParams);
        }

        $order && $query->orderBy($order);
        $query->limit(1);

        return $query->run($fetch)->row(0);
    }

    /**
     * Select all rows from given table as `array` or return `null` if no matches.
     *
     * @param  string                    $table
     * @param  string                    $fields
     * @param  string|array|null         $where
     * @param  any|null                  $whereParams
     * @param  string|null               $order
     * @param  int|array<int>|null       $limit
     * @param  string|array<string>|null $fetch
     * @return ?array
     */
    public function selectAll(string $table, string $fields = '*', $where = null, $whereParams = null,
        string $order = null, $limit = null, $fetch = null): ?array
    {
        $query = $this->initQuery($table)->select($fields);

        if ($where) {
            [$where, $whereParams] = $this->prepareWhereInput($where, $whereParams);
            $query->where($where, $whereParams);
        }

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
     * @return int|array|object|null
     */
    public function insert(string $table, array $data, array $options = null)
    {
        $return = $fetch = $batch = $conflict = $sequence = null;
        if ($options != null) {
            @ ['return' => $return, 'fetch' => $fetch, 'batch' => $batch,
                'conflict' => $conflict, 'sequence' => $sequence] = $options;
        }

        $query = $this->initQuery($table)->insert($data, $batch, $sequence);

        if ($conflict) {
            if (isset($conflict['fields']) || isset($conflict['action'])) {
                @ ['fields' => $fields, 'action' => $action] = $conflict;
            } else {
                @ [$fields, $action, $update, $where] = $conflict;
            }

            // Eg: 'conflict' => ['id', 'action' => 'nothing'] or ['id', 'update' => [..], 'where' => [..]].
            $fields ??= $conflict[0] ?? null;
            $update ??= $conflict['update'] ?? null;
            $where ??= $conflict['where'] ?? null;

            // Can be skipped with update.
            $update && $action = 'update';

            if (!$action) {
                throw new DatabaseException("Conflict action is not given");
            }
            if (!$update && strtolower($action) == 'update') {
                throw new DatabaseException("Conflict action is 'update', but no update data given");
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
                $resultArray = (array) $result;
                if (isset($resultArray[$return])) {
                    $result = $resultArray[$return];
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
     * @param  any|null          $whereParams
     * @param  array|null        $options
     * @return int|array|object|null
     */
    public function update(string $table, array $data, $where = null, $whereParams = null, array $options = null)
    {
        $return = $fetch = $batch = $limit = null;
        if ($options != null) {
            @ ['return' => $return, 'fetch' => $fetch, 'batch' => $batch,
                'limit' => $limit] = $options;
        }

        $query = $this->initQuery($table)->update($data);

        if ($where) {
            [$where, $whereParams] = $this->prepareWhereInput($where, $whereParams);
            $query->where($where, $whereParams);
        }

        $return && $query->return($return, $fetch);
        $limit && $query->limit($limit);

        $result = $query->run();

        // If rows wanted as return.
        if ($return) {
            if ($batch) {
                $result = $result->rows();
            } else {
                // If single row wanted as return.
                $result = $result->row(0);
                $resultArray = (array) $result;
                if (isset($resultArray[$return])) {
                    $result = $resultArray[$return];
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
     * @param  array|null        $whereParams
     * @param  array|null        $options
     * @return int|array|object|null
     */
    public function delete(string $table, $where = null, $whereParams = null, array $options = null)
    {
        $return = $fetch = $batch = $limit = null;
        if ($options != null) {
            @ ['return' => $return, 'fetch' => $fetch, 'batch' => $batch,
                'limit' => $limit] = $options;
        }

        $query = $this->initQuery($table)->delete();

        if ($where) {
            [$where, $whereParams] = $this->prepareWhereInput($where, $whereParams);
            $query->where($where, $whereParams);
        }

        $return && $query->return($return, $fetch);
        $limit && $query->limit($limit);

        $result = $query->run();

        // If rows wanted as return.
        if ($return) {
            if ($batch) {
                $result = $result->rows();
            } else {
                // If single row wanted as return.
                $result = $result->row(0);
                $resultArray = (array) $result;
                if (isset($resultArray[$return])) {
                    $result = $resultArray[$return];
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
     * Run a count query with/without given conditions.
     *
     * @param  string     $query
     * @param  array|null $queryParams
     * @return int
     */
    public function countQuery(string $query, array $queryParams = null): int
    {
        $result = $this->get('SELECT count(*) AS c FROM ('. $query .') AS t', $queryParams, 'array');

        return (int) ($result['c'] ?? 0);
    }

    /**
     * Run a transaction or return a `Transaction` object.
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
        return "'". $in ."'";
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
            $rpos = strpos($in, ')');
            if (!$rpos) { // Not parsed array[(foo, ..)] stuff, sorry.
                throw new DatabaseException("Unclosed parenthesis in '%s' input", $in);
            }

            $name = substr($in, 1, $rpos - 1); // Eg: part foo of (foo).
            $rest = substr($in, $rpos + 1) ?: ''; // Eg: part ::int of (foo)::int.

            return '('. $this->quoteNames($name) .')'. $rest;
        }

        // Dot notations (eg: foo.id => "foo"."id").
        $pos = strpos($in, '.');
        if ($pos) {
            return $this->quoteNames(substr($in, 0, $pos)) .'.'.
                   $this->quoteNames(substr($in, $pos + 1));
        }

        $pdoDriver = $this->link->pdoDriver();
        if ($pdoDriver == 'pgsql') {
            // Cast notations (eg: foo::int).
            if ($pos = strpos($in, '::')) {
                return $this->quoteName(substr($in, 0, $pos)) . substr($in, $pos);
            }

            // Array notations (eg: foo[], foo[1] or array[foo, bar]).
            if ($pos = strpos($in, '[')) {
                $name = substr($in, 0, $pos);
                $rest = substr($in, $pos + 1);

                return (strtolower($name) == 'array')
                     ? $name .'['. $this->quoteNames($rest)
                     : $this->quoteName($name) .'['. $rest;
            }
        }

        switch ($pdoDriver) {
            case 'mysql': return '`'. $in .'`';
            case 'mssql': return '['. $in .']';
                 default: return '"'. $in .'"';
        }
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
        $type = gettype($in);

        if ($type == 'array' && $format != '?a') {
            return array_map(
                fn($in) => $this->escape($in, $format),
                $in
            );
        } elseif ($type == 'object') {
            switch ($class = get_class($in)) {
                case Sql::class:   return $in->content();
                case Name::class:  return $this->escapeName($in->content());
                case Query::class: return $in->toString();
                default:
                    throw new DatabaseException("Invalid input object '%s' given, valids are: "
                        . "Query, sql\{Sql, Name}", $class);
            }
        }

        // Available placeholders are "?, ?? / ?s, ?i, ?f, ?b, ?n, ?r, ?a".
        if ($format) {
            if ($format == '?' || $format == '??') {
                return ($format == '?') ? $this->escape($in) : $this->escapeName($in);
            }

            switch ($format) {
                case '?s': return $this->escapeString((string) $in);
                case '?i': return (int) $in;
                case '?f': return (float) $in;
                case '?b': return $in ? 'true' : 'false';
                case '?r': return $in; // Raw.
                case '?n': return $this->escapeName($in);
                case '?a': return join(', ', (array) $this->escape($in)); // Array.
            }

            throw new DatabaseException("Unimplemented input format '%s'", $format);
        }

        switch ($type) {
            case 'NULL':    return 'NULL';
            case 'string':  return $this->escapeString($in);
            case 'integer': return $in;
            case 'double':  return $in;
            case 'boolean': return $in ? 'true' : 'false';
            default:
                throw new DatabaseException("Unimplemented input type '%s'", $type);
        }
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

        if (!$quote) {
            $out = trim($out, "'");
        }
        if ($extra != '') {
            $out = addcslashes($out, $extra);
        }

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
        switch ($this->link->pdoDriver()) {
            case 'mysql': $in = str_replace('`', '``', $in); break;
            case 'mssql': $in = str_replace(']', ']]', $in); break;
                 default: $in = str_replace('"', '""', $in);
        }

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
        $aliases = array_filter($match[3], 'strlen');
        if (!$names) {
            return $in;
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
     * Prepare given input returning a `string`.
     *
     * @param  string     $in
     * @param  array|null $params
     * @return string
     * @throws froq\database\DatabaseException
     */
    public function prepare(string $in, array $params = null): string
    {
        $out = $this->preparePrepareInput($in);
        if ($out == '') {
            throw new DatabaseException("Empty input given to '%s()' for preparation", __method__);
        }

        // Available placeholders are "?, ?? / ?s, ?i, ?f, ?b, ?n, ?r, ?a / :foo, :foo_bar".
        static $pattern = '~
              \?[sifbnra](?![\w]) # Scalars/(n)ame/(r)aw. Eg: ("id = ?i", ["1"]) or ("?n = ?i", ["id", "1"]).
            | \?\?                # Names (identifier).   Eg: ("?? = ?", ["id", 1]).
            | \?(?![\w&|])        # Any type.             Eg: ("id = ?", [1]), but not "id ?| array[..]" for PgSQL.
            | (?<!:):\w+          # Named parameters.     Eg: ("id = :id", [1]), but not "id::int" casts for PgSQL.
        ~xu';

        if (preg_match_all($pattern, $out, $match)) {
            if ($params == null) {
                throw new DatabaseException("Empty input parameters given to '%s()', non-empty input parameters "
                    . "required when input contains parameter placeholders like ?, ?? or :foo", __method__);
            }

            $i = 0;
            $keys = $values = [];
            $holders = array_filter($match[0]);

            foreach ($holders as $holder) {
                $pos = strpos($holder, ':');
                if ($pos > -1) { // Named.
                    $key = trim($holder, ':');
                    if (!array_key_exists($key, $params)) {
                        throw new DatabaseException("Replacement key '%s' not found in given parameters", $key);
                    }

                    $value = $this->escape($params[$key]);
                    if (is_array($value)) {
                        $value = join(', ', $value);
                    }

                    $keys[] = '~:'. $key .'~';
                    $values[] = $value;
                } else { // Question-mark.
                    if (!array_key_exists($i, $params)) {
                        throw new DatabaseException("Replacement index '%s' not found in given parameters", $i);
                    }

                    $value = $this->escape($params[$i++], $holder);
                    if (is_array($value)) {
                        $value = join(', ', $value);
                    }

                    $keys[] = '~'. preg_quote($holder) .'(?![|&])~'; // PgSQL operators.
                    $values[] = $value;
                }
            }

            $out = preg_replace($keys, $values, $out, 1);
        }

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
        $out = $this->preparePrepareInput($in);
        if ($out == '') {
            throw new DatabaseException("Empty input given to '%s()' for preparation", __method__);
        }

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
     * Prepare a where input.
     *
     * @param  string|array $in
     * @param  any|null     $params
     * @return array
     * @since  4.15
     * @throws froq\database\DatabaseException
     */
    private function prepareWhereInput($in, $params = null): array
    {
        [$where, $whereParams] = [$in, $params];

        if ($where != null) {
            // Note: "where" must not be combined when array given, eg: (["a = ? AND b = ?" => [1, 2]])
            // will not be prepared and prepare() method will throw exception about replacement index. So
            // use ("a = ? AND b = ?", [1, 2]) convention instead for multiple conditions.
            if (is_array($where)) {
                $temp = [];
                foreach ($where as $key => $value) {
                    if (!is_string($key)) {
                        throw new DatabaseException("Invalid where input, use ('a = ? AND b = ?', [1, 2]) "
                            . "convention");
                    }

                    // Check whether a placeholder given or not (eg: ["a" => 1]).
                    if (strpbrk($key, '?:') === false) {
                        $key .= ' = ?';
                    }

                    $temp[$key] = $value;
                }

                $where = join(' AND ', array_keys($temp));
                $whereParams = array_values($temp);
            } elseif (is_string($where)) {
                $where = trim($where);
                $whereParams = (array) $whereParams;
            } else {
                throw new DatabaseException("Invalid where input '%s', valids are: string, array",
                    gettype($where));
            }
        }

        return [$where, $whereParams];
    }

    /**
     * Prepare a prepare input escaping names only eg. `@id`.
     *
     * @param  string $in
     * @return string
     */
    private function preparePrepareInput(string $in): string
    {
        $out = trim($in);

        if ($out != '') {
            // Prepare names (eg: '@id = ?', 1 or '@[id,..]') .
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
}
