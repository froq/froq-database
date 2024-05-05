<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database;

use froq\database\sql\{Sql, Name};
use froq\database\result\{Row, Rows};
use froq\database\common\{Profiler, Platform};
use froq\database\trait\StatementTrait;
use froq\common\trait\FactoryTrait;
use froq\log\Logger;
use PDO, PDOStatement, PDOException, Throwable;

/**
 * A database worker class, provides some utility methods for such operations CRUD'ing
 * and preparing queries, querying/executing SQL commands.
 *
 * @package froq\database
 * @class   froq\database\Database
 * @author  Kerem Güneş
 * @since   1.0, 4.0
 */
class Database
{
    use FactoryTrait, StatementTrait;

    /** Logger instance. */
    public readonly ?Logger $logger;

    /** Profiler instance. */
    public readonly ?Profiler $profiler;

    /** Platform instance. */
    public readonly Platform $platform;

    /** Link instance */
    public readonly Link $link;

    /** Options. */
    private array $options;

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        // Default is null (no logging).
        $logging = array_pluck($options, 'logging');
        if ($logging) {
            $this->logger = new Logger($logging);
            $this->logger->setOption('slowQuery', $logging['slowQuery'] ?? 0);
        } else {
            $this->logger = null;
        }

        // Default is false (no profiling).
        $profiling = array_pluck($options, 'profiling');
        if ($profiling) {
            $this->profiler = new Profiler();
        } else {
            $this->profiler = null;
        }

        // Grab driver name.
        if (isset($options['dsn'])) {
            $driver = strbcut($options['dsn'], ':');
            $this->platform = new Platform($driver);
        }

        $this->options = $options;

        // Add to registry (if no default database was set yet).
        DatabaseRegistry::hasDefault() || DatabaseRegistry::setDefault($this);
    }

    /**
     * Hide all debug info.
     */
    public function __debugInfo()
    {}

    /**
     * Get link property connecting if no link yet.
     *
     * @return froq\database\Link
     * @throws froq\database\DatabaseLinkException
     */
    public function link(): Link
    {
        if (empty($this->link)) {
            $this->link = Link::init($this->options);
            unset($this->options); // Used already.

            try {
                empty($this->profiler) ? $this->link->connect()
                    : $this->profiler->profileLink(fn() => $this->link->connect());
            } catch (LinkException $e) {
                throw new DatabaseLinkException($e);
            }
        }

        return $this->link;
    }

    /**
     * Run a SQL query and returning its result as `Result` object, or throw a
     * `DatabaseQueryException` if any query error occurs.
     *
     * @param  string|Query $query
     * @param  array|null   $params
     * @param  array|null   $options
     * @return froq\database\Result
     * @throws froq\database\{DatabaseException|DatabaseQueryException}
     */
    public function query(string|Query $query, array $params = null, array $options = null): Result
    {
        $query = $params ? $this->prepare((string) $query, $params) : trim((string) $query);
        $query || throw new DatabaseException('Empty query');

        try {
            $timeop = $this->logger?->getOption('slowQuery');
            if ($timeop) {
                $marker = Profiler::marker('slowQuery');
                Profiler::mark($marker);
            }

            $pdo          = $this->link()->pdo();
            $pdoStatement = empty($this->profiler) ? $pdo->query($query)
                : $this->profiler->profileQuery($query, fn() => $pdo->query($query));

            if ($timeop) {
                $time = Profiler::unmark($marker);
                if ($time > $timeop) {
                    $this->logger->logWarn("Slow query: time {$time}, {$query}");
                }
            }

            return new Result($pdo, $pdoStatement, $options, $this);
        } catch (PDOException $e) {
            throw new DatabaseQueryException($e);
        }
    }

    /**
     * Run a SQL query and returning its result as `int` or `null`, or throw a
     * `DatabaseQueryException` if any error occurs.
     *
     * @param  string|Query $query
     * @param  array|null   $params
     * @return int
     * @throws froq\database\{DatabaseException|DatabaseQueryException}
     */
    public function execute(string|Query $query, array $params = null): int
    {
        $query = $params ? $this->prepare((string) $query, $params) : trim((string) $query);
        $query || throw new DatabaseException('Empty query');

        try {
            $timeop = $this->logger?->getOption('slowQuery');
            if ($timeop) {
                $marker = Profiler::marker('slowQuery');
                Profiler::mark($marker);
            }

            $pdo       = $this->link()->pdo();
            $pdoResult = empty($this->profiler) ? $pdo->exec($query)
                : $this->profiler->profileQuery($query, fn() => $pdo->exec($query));

            if ($timeop) {
                $time = Profiler::unmark($marker);
                if ($time > $timeop) {
                    $this->logger->logWarn("Slow query: time {$time}, {$query}");
                }
            }

            // Extra check for unknown stuff.
            if ($pdoResult === false) {
                $errorCode = $pdo->errorCode();
                if ($errorCode !== '00000' || $errorCode !== '01000') {
                    throw new PDOException('Unknown error');
                }
            }

            return $pdoResult;
        } catch (PDOException $e) {
            throw new DatabaseQueryException($e);
        }
    }

    /**
     * Get a single row running given query or return `null` if no match.
     *
     * @param  string|Query     $query
     * @param  array|null       $params
     * @param  string|null      $fetch
     * @param  string|bool|null $flat
     * @return mixed
     */
    public function get(string|Query $query, array $params = null, string $fetch = null, string|bool $flat = null): mixed
    {
        $row = $this->query($query, $params, ['fetch' => $fetch])->rows(0);

        // When a single column value wanted.
        $flat && $row = $this->getFlattenData($flat, $row, 1);

        return $row;
    }

    /**
     * Get all rows running given query or return `null` if no matches.
     *
     * @param  string|Query     $query
     * @param  array|null       $params
     * @param  string|null      $fetch
     * @param  string|bool|null $flat
     * @param  bool             $raw For returning a raw Result instance.
     * @return mixed
     */
    public function getAll(string|Query $query, array $params = null, string $fetch = null, string|bool $flat = null,
        bool $raw = false): mixed
    {
        $result = $this->query($query, $params, ['fetch' => $fetch]);
        if ($raw) {
            return $result;
        }

        $rows = $result->rows();

        // When a single column value wanted.
        $flat && $rows = $this->getFlattenData($flat, $rows);

        return $rows;
    }

    /**
     * Bridge method to `getAll()` for returning a `Result` instance.
     *
     * @param  mixed ...$args Same as in `getAll()` method.
     * @return froq\database\Result
     * @since  6.0
     */
    public function getResult(string|Query $query, mixed ...$args): Result
    {
        $args['raw'] = true;

        return $this->getAll($query, ...$args);
    }

    /**
     * Bridge method to `getResult()` for returning a `Row` instance.
     *
     * @param  string   $query
     * @param  mixed ...$args Same as in `getResult()` method.
     * @return froq\database\result\Row|null
     * @since  6.0
     */
    public function getRow(string|Query $query, mixed ...$args): Row|null
    {
        // Merge with constant defaults.
        $args = [...$args, 'fetch' => 'array', 'flat' => null];

        return $this->getResult($query, ...$args)->rows(0, true);
    }

    /**
     * Bridge method to `getResult()` for returning a `Row` instance.
     *
     * @param  string   $query
     * @param  mixed ...$args Same as in `getResult()` method.
     * @return froq\database\result\Rows
     * @since  6.0
     */
    public function getRows(string|Query $query, mixed ...$args): Rows
    {
        // Merge with constant defaults.
        $args = [...$args, 'fetch' => 'array', 'flat' => null];

        return $this->getResult($query, ...$args)->rows(null, true);
    }

    /**
     * Select a row from given table or return `null` if no match.
     *
     * @param  string            $table
     * @param  string|array      $fields
     * @param  string|array|null $where
     * @param  array|null        $params
     * @param  string|null       $order
     * @param  string|null       $fetch
     * @param  string|bool|null  $flat
     * @param  string|null       $op
     * @return mixed
     */
    public function select(string $table, string|array $fields = '*', string|array $where = null, array $params = null,
        string $order = null, string $fetch = null, string|bool $flat = null, string $op = null): mixed
    {
        $query = $this->initQuery($table)->select($fields);

        $where && $query->where(...$this->prepareWhereInput($where, $params, $op));
        $order && $query->orderBy($order);
        $query->limit(1);

        $row = $query->run($fetch)->rows(0);

        // When a single column value wanted.
        $flat && $row = $this->getFlattenData($flat, $row, 1);

        return $row;
    }

    /**
     * Select all rows from given table or return `null` if no matches.
     *
     * @param  string            $table
     * @param  string            $fields
     * @param  string|array|null $where
     * @param  array|null        $params
     * @param  string|null       $order
     * @param  int|array|null    $limit
     * @param  string|null       $fetch
     * @param  string|bool|null  $flat
     * @param  string|null       $op
     * @param  bool              $raw For returning a raw Result instance.
     * @return mixed
     */
    public function selectAll(string $table, string $fields = '*', string|array $where = null, array $params = null,
        string $order = null, int|array $limit = null, string $fetch = null, string|bool $flat = null,
        string $op = null, bool $raw = false): mixed
    {
        $query = $this->initQuery($table)->select($fields);

        $where && $query->where(...$this->prepareWhereInput($where, $params, $op));
        $order && $query->orderBy($order);
        $limit && $query->limit(...(array) $limit);

        $result = $query->run($fetch);
        if ($raw) {
            return $result;
        }

        $rows = $result->rows();

        // When a single column value wanted.
        $flat && $rows = $this->getFlattenData($flat, $rows);

        return $rows;
    }

    /**
     * Bridge method to `selectAll()` for returning a `Result` instance.
     *
     * @param  string   $table
     * @param  mixed ...$args Same as in `selectAll()` method.
     * @return froq\database\Result
     * @since  6.0
     */
    public function selectResult(string $table, mixed ...$args): Result
    {
        $args['raw'] = true;

        return $this->selectAll($table, ...$args);
    }

    /**
     * Bridge method to `selectResult()` for returning a `Row` instance.
     *
     * @param  string   $table
     * @param  mixed ...$args Same as in `selectResult()` method.
     * @return froq\database\result\Row|null
     * @since  6.0
     */
    public function selectRow(string $table, mixed ...$args): Row|null
    {
        // Merge with constant defaults.
        $args = [...$args, 'fetch' => 'array', 'flat' => null, 'limit' => 1];

        return $this->selectResult($table, ...$args)->rows(0, true);
    }

    /**
     * Bridge method to `selectResult()` for returning a `Rows` instance.
     *
     * @param  string   $table
     * @param  mixed ...$args Same as in `selectResult()` method.
     * @return froq\database\result\Rows
     * @since  6.0
     */
    public function selectRows(string $table, mixed ...$args): Rows
    {
        // Merge with constant defaults.
        $args = [...$args, 'fetch' => 'array', 'flat' => null];

        return $this->selectResult($table, ...$args)->rows(null, true);
    }

    /**
     * Insert row/rows to given table.
     *
     * @param  string $table
     * @param  array  $data
     * @param  array  $options
     * @return mixed
     * @throws froq\database\DatabaseException
     */
    public function insert(string $table, array $data, array $options = null): mixed
    {
        $return = $fetch = $batch = $conflict = $sequence = null;
        if ($options) {
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
            if (!$update && strtolower($action) === 'update') {
                throw new DatabaseException('Conflict action is update, but no update data given');
            }

            $query->conflict($fields, $action, $update, $where);
        }

        $return && $query->return($return, $fetch);

        $result = $query->run();

        return $this->getReturningData($result, $return, $batch, $sequence);
    }

    /**
     * Update row/rows on given table.
     *
     * @param  string            $table
     * @param  array             $data
     * @param  string|array|null $where
     * @param  array|null        $params
     * @param  array|null        $options
     * @param  string|null       $op
     * @return mixed
     */
    public function update(string $table, array $data, string|array $where = null, array $params = null,
        array $options = null, string $op = null): mixed
    {
        $return = $fetch = $batch = $limit = null;
        if ($options) {
            [$return, $fetch, $batch, $limit] = array_select(
                $options, ['return', 'fetch', 'batch', 'limit']
            );
        }

        $query = $this->initQuery($table)->update($data);

        $where  && $query->where(...$this->prepareWhereInput($where, $params, $op));
        $return && $query->return($return, $fetch);
        $limit  && $query->limit($limit);

        $result = $query->run();

        return $this->getReturningData($result, $return, $batch);
    }

    /**
     * Delete row/rows from given table.
     *
     * @param  string            $table
     * @param  string|array|null $where
     * @param  array|null        $params
     * @param  array|null        $options
     * @param  string|null       $op
     * @return mixed
     */
    public function delete(string $table, string|array $where = null, array $params = null, array $options = null,
        string $op = null): mixed
    {
        $return = $fetch = $batch = $limit = null;
        if ($options) {
            [$return, $fetch, $batch, $limit] = array_select(
                $options, ['return', 'fetch', 'batch', 'limit']
            );
        }

        $query = $this->initQuery($table)->delete();

        $where  && $query->where(...$this->prepareWhereInput($where, $params, $op));
        $return && $query->return($return, $fetch);
        $limit  && $query->limit($limit);

        $result = $query->run();

        return $this->getReturningData($result, $return, $batch);
    }

    /**
     * Count all rows on given table with/without given conditions.
     *
     * @param  string            $table
     * @param  string|array|null $where
     * @param  array|null        $params
     * @param  string|null       $op
     * @return int
     */
    public function count(string $table, string|array $where = null, array $params = null, string $op = null): int
    {
        $query = $this->initQuery($table);

        $where && $query->where(...$this->prepareWhereInput($where, $params, $op));

        return $query->count();
    }

    /**
     * Run a count query with/without given conditions.
     *
     * @param  string|Query $query
     * @param  array|null   $params
     * @return int
     */
    public function countQuery(string|Query $query, array $params = null): int
    {
        $result = $this->get('SELECT count(*) AS c FROM (' . $query . ') AS t', $params, 'array');

        return (int) ($result['c'] ?? 0);
    }

    /**
     * Increase given field/fields value, optionally returning current value or affected rows count.
     *
     * @param  string     $table
     * @param  array      $field
     * @param  int|float  $value
     * @param  array|null $where
     * @param  array|null $params
     * @param  array|null $options
     * @return mixed
     */
    public function increase(string $table, string|array $field, int|float $value = 1, string|array $where = null,
        array $params = null, array $options = null): mixed
    {
        return $this->doIncreaseDecrease('increase', $table, $field, $value, $where, $params, $options);
    }

    /**
     * Decrease given field/fields value, optionally returning current value or affected rows count.
     *
     * @param  string     $table
     * @param  array      $field
     * @param  int|float  $value
     * @param  array|null $where
     * @param  array|null $params
     * @param  array|null $options
     * @return mixed
     */
    public function decrease(string $table, string|array $field, int|float $value = 1, string|array $where = null,
        array $params = null, array $options = null): mixed
    {
        return $this->doIncreaseDecrease('decrease', $table, $field, $value, $where, $params, $options);
    }

    /**
     * Run a transaction in a try/catch block or return a `Transaction` object.
     *
     * @param  callable|null $call      Eg: fn(Database $db) ...
     * @param  callable|null $callError Eg: fn(Throwable $e, ?Database $db) ...
     * @return mixed
     * @throws Throwable
     */
    public function transaction(callable $call = null, callable $callError = null): mixed
    {
        $transaction = new Transaction($this->link()->pdo());

        // Return transaction object.
        if (!$call) {
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
            if ($callError) {
                return $callError($e, $this);
            }

            throw $e;
        }
    }

    /**
     * Quote an input.
     *
     * @param  string $input
     * @return string
     */
    public function quote(string $input): string
    {
        return $this->link()->pdo()->quote($input);
    }

    /**
     * Quote a name input.
     *
     * @param  string $input
     * @return string
     */
    public function quoteName(string $input): string
    {
        if ($input === '*') {
            return $input;
        }

        if ($input && $input[0] === '@') {
            $input = substr($input, 1);
        }

        // For row(..) or other parenthesis stuff.
        if (strpos($input, '(') === 0) {
            $rpos = strpos($input, ')'); // Not parsed array[(foo, ..)] stuff, sorry.
            $rpos || throw new DatabaseException('Unclosed parenthesis in %q input', $input);

            $name = substr($input, 1, $rpos - 1); // Eg: part foo of (foo).
            $rest = substr($input, $rpos + 1) ?: ''; // Eg: part ::int of (foo)::int.

            return '(' . $this->quoteNames($name) . ')' . $rest;
        }

        // Dot notations (eg: foo.id => "foo"."id").
        $pos = strpos($input, '.');
        if ($pos) {
            return $this->quoteNames(substr($input, 0, $pos)) . '.' .
                   $this->quoteNames(substr($input, $pos + 1));
        }

        if ($this->platform->equals('pgsql')) {
            // Cast notations (eg: foo::int).
            if ($pos = strpos($input, '::')) {
                return $this->quoteName(substr($input, 0, $pos)) . substr($input, $pos);
            }

            // Array notations (eg: foo[], foo[1] or array[foo, bar]).
            if ($pos = strpos($input, '[')) {
                $name = substr($input, 0, $pos);
                $rest = substr($input, $pos + 1);
                $last = '';
                if (strpos($rest, ']')) {
                    $rest = substr($input, $pos + 1, -1);
                    $last = ']';
                }

                return (strtolower($name) === 'array')
                     ? $name . '[' . $this->quoteNames($rest) . $last
                     : $this->quoteName($name) . '[' . $rest . $last;
            }
        }

        return $this->platform->quoteName($input);
    }

    /**
     * Quote names in given input.
     *
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
     * Escape an input with/without given format.
     *
     * @param  mixed       $input
     * @param  string|null $format
     * @return mixed
     * @throws froq\database\DatabaseException
     */
    public function escape(mixed $input, string $format = null): mixed
    {
        $format = (string) $format;

        if (is_array($input) && $format !== '?a') {
            return array_map(
                fn(mixed $input): mixed => $this->escape($input, $format),
                $input
            );
        }

        if (is_object($input)) {
            return match (true) {
                // Internals.
                ($input instanceof Sql) => $input->content(),
                ($input instanceof Name) => $this->escapeName($input->content()),
                ($input instanceof Query) => '(' . $input->toString() . ')',

                // Externals.
                ($input instanceof \Stringable) => $this->escapeString((string) $input),
                ($input instanceof \DateTimeInterface) => $this->escapeString($input->format('Y-m-d H:i:s')),

                default => throw new DatabaseException(
                    'Invalid input object %q [valids: %A]',
                    [$input::class, [Query::class, Sql::class, Name::class, 'Stringable', 'DateTimeInterface']]
                )
            };
        }

        // Available formats: ?, ??, ?s, ?i, ?f, ?b, ?r, ?n, ?a.
        if ($format !== '') {
            return match ($format) {
                '?'  => $this->escape($input),
                '??' => $this->escapeName($input),
                '?s' => $this->escapeString((string) $input),
                '?i' => (int) $input,
                '?f' => (float) $input,
                '?b' => $input ? 'true' : 'false',
                '?r' => $input, // Raw input.
                '?n' => $this->escapeName($input),
                '?a' => join(', ', (array) $this->escape($input)),

                default => throw new DatabaseException(
                    'Unimplemented input format %q', $format
                )
            };
        }

        // Internal types.
        return match ($type = get_type($input)) {
            'null'         => 'NULL',
            'string'       => $this->escapeString($input),
            'int', 'float' => $input,
            'bool'         => $input ? 'true' : 'false',

            default => throw new DatabaseException(
                'Unimplemented input type %q', $type
            )
        };
    }

    /**
     * Escape a string input.
     *
     * @param  string $input
     * @param  bool   $wrap
     * @param  string $extra
     * @return string
     */
    public function escapeString(string $input, bool $wrap = true, string $extra = ''): string
    {
        $input = $this->quote($input);

        $wrap  || $input = trim($input, "'");
        $extra && $input = addcslashes($input, $extra);

        return $input;
    }

    /**
     * Escape like string input.
     *
     * @param  string $input
     * @param  bool   $wrap
     * @return string
     */
    public function escapeLikeString(string $input, bool $wrap = true): string
    {
        return $this->escapeString($input, $wrap, '%_');
    }

    /**
     * Escape a name input.
     *
     * @param  string $input
     * @return string
     */
    public function escapeName(string $input): string
    {
        $input = $this->platform->escapeName($input);

        return $this->quoteName($input);
    }

    /**
     * Escape names in given input.
     *
     * @param  string $input
     * @return string
     */
    public function escapeNames(string $input): string
    {
        // Eg: "id, name ..." or "id as ID, ...".
        preg_match_all('~([^\s,]+)(?:\s+(?:(AS)\s+)?([^\s,]+))?~i', $input, $match);

        $names = array_filter($match[1], 'strlen');
        if (!$names) {
            return $input;
        }

        $aliases = array_filter($match[3], 'strlen');
        foreach ($names as $i => $name) {
            $names[$i] = $this->escapeName($name);
            if (isset($aliases[$i])) {
                $names[$i] .= ' AS ' . $this->escapeName($aliases[$i]);
            }
        }

        return join(', ', $names);
    }

    /**
     * Prepare given input returning a string output.
     *
     * @param  string     $input
     * @param  array|null $params
     * @return string
     * @throws froq\database\DatabaseException
     */
    public function prepare(string $input, array $params = null): string
    {
        $input = $this->prepareNameInput($input);
        $input || throw new DatabaseException('Empty input');

        // Available placeholders: ?, ?? / ?s, ?i, ?f, ?b, ?n, ?r, ?a / :foo, :foo_bar.
        static $pattern = '~
            # Scalars/(n)ame/(r)aw. Eg: ("id = ?i", ["1"]) or ("?n = ?i", ["id", "1"]).
            \?[sifbnra](?![\w])
            # Names (identifier). Eg: ("?? = ?", ["id", 1]).
            | \?\?
            # Any type with/without format. Eg: ("id = ?", [1]), ("id = ?1 OR id = ?1", [1]), not "id ?| array[..]" for PgSQL.
            | \?(?![a-z&|])([\d][sifbnra]?)?
            # Named parameters. Eg: ("id = :id", [1]), but not "id::int" casts for PgSQL.
            | (?<!:):\w+
        ~x';

        if (preg_match_all($pattern, $input, $match)) {
            if (!$params) {
                throw new DatabaseException(
                    'Empty input parameters (input parameters required when '.
                    'input contains parameter placeholders like ?, ?? or :foo)'
                );
            }

            $i = 0;
            $keys = $values = $used = [];
            $holders = array_filter($match[0]);

            // Eg: ('id = ?1 OR id = ?1', [123]) or ('id = ?1i', [123]).
            foreach ($holders as $ii => $holder) {
                $pos = $holder[1] ?? '';
                if (ctype_digit($pos)) {
                    $ipos = $pos - 1;
                    if (!array_key_exists($ipos, $params)) {
                        throw new DatabaseException(
                            'Replacement #%i not found in given parameters', $ipos
                        );
                    }

                    $format = $holder[2] ?? '';
                    $format && $format = '?' . $format;

                    $value = $this->escape($params[$ipos], $format);
                    while (($pos = strpos($input, $holder)) !== false) {
                        $input = substr_replace($input, strval($value), $pos, strlen($holder));
                        array_push($used, $ipos);
                    }

                    // Drop used holder.
                    unset($holders[$ii]);
                }
            }

            // Drop used items by their indexes & re-form params.
            if ($used) {
                array_unset($params, ...$used);
                $params = array_slice($params, 0);
            }

            foreach ($holders as $holder) {
                // Named.
                if ($holder[0] === ':') {
                    $key = substr($holder, 1);
                    if (!array_key_exists($key, $params)) {
                        throw new DatabaseException(
                            'Replacement key %q not found in given parameters', $key
                        );
                    }

                    $value = $this->escape($params[$key]);
                    if (is_array($value)) {
                        $value = join(', ', $value);
                    }

                    $keys[]   = '~:' . $key . '~';
                    $values[] = $value;
                }
                // Question-mark.
                else {
                    if (!array_key_exists($i, $params)) {
                        throw new DatabaseException(
                            'Replacement index %q not found in given parameters', $i
                        );
                    }

                    $value = $this->escape($params[$i++], $holder);
                    if (is_array($value)) {
                        $value = join(', ', $value);
                    }

                    $keys[]   = '~' . preg_quote($holder) . '(?![&|])~'; // PgSQL operators.
                    $values[] = $value;
                }
            }

            $input = preg_replace($keys, $values, $input, 1);
        }

        return $input;
    }

    /**
     * Prepare given input returning a string output with escaped names.
     *
     * @param  string $input
     * @return string
     * @since  5.0
     */
    public function prepareName(string $input): string
    {
        $input = $this->prepareNameInput($input);
        $input || throw new DatabaseException('Empty input');

        return $input;
    }

    /**
     * Init a `Sql` object with/without given params to prepare.
     *
     * @param  string     $input
     * @param  array|null $params
     * @return froq\database\sql\Sql
     */
    public function initSql(string $input, array $params = null): Sql
    {
        $params && $input = $this->prepare($input, $params);

        return new Sql($input);
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
     * Prepare an input escaping names only (eg: `@id => "id"` for PgSQL).
     */
    private function prepareNameInput(string $input): string
    {
        $input = trim($input);

        if ($input !== '') {
            if (str_contains($input, '@')) {
                $input = preg_replace_callback(
                    // Eg: '@id', '@u.id', or multi '@[id, ..]'.
                    '~(?=[\.\[\]]?\w*|^)@([\w][\w\.\[\]]*)|@\[.+?\]~',
                    function ($match) {
                        if (count($match) === 1) {
                            return $this->escapeNames(substr($match[0], 2, -1));
                        }
                        return $this->escapeName($match[1]);
                    }
                , $input);
            }
        }

        return $input;
    }

    /**
     * Prepare a where input.
     *
     * @throws froq\database\DatabaseException
     */
    private function prepareWhereInput(string|array $input, array $params = null, string $op = null): array
    {
        $where = $input;

        if ($where) {
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
                    if (in_array($field[-1], $signs, true)) {
                        $sign  = format(' %s ', $field[-1] === '!' ? '!=' : $field[-1]);
                        $field = substr($field, 0, -1);
                    }

                    // $type = '';
                    // if (str_contains($field, '::')) {
                    //     [$field, $type] = explode('::', $field);
                    // }

                    // ctype_alnum($field) || throw new DatabaseException(
                    //     'Invalid field name %q in where input, use an alphanumeric name', $field
                    // );

                    if (is_array($value)) {
                        $value = str_contains($sign, '!')
                            ? new Sql('NOT IN (' . join(', ', $this->escape($value)) . ')')
                            : new Sql('IN (' . join(', ', $this->escape($value)) . ')');
                        $sign  = ' ';
                    }

                    // $type && $field .= '::' . $type;

                    // Add placeholders.
                    $field = $this->quoteName($field) . $sign . '?';

                    $temp[$field] = $value;
                }

                $where  = join(' ' . ($op ?: 'AND') . ' ', array_keys($temp));
                $params = array_values($temp);
            }
        }

        return [$where, $params];
    }

    /**
     * Get flatten data reducing fields to single dimension.
     */
    private function getFlattenData(string|bool $flat, array|object|null $data, int $limit = null): mixed
    {
        if ($data) {
            $data = is_list($data) ? $data : (array) $data;
            if ($limit === 1) {
                $data = array_select($data, is_string($flat) ? $flat : key($data));
            } else {
                $data = array_column($data, is_string($flat) ? $flat : key($data[0]));
            }
        }

        return $data;
    }

    /**
     * Get returning data for insert/update/delete methods.
     */
    private function getReturningData(Result $result, string|array|bool|null $return, bool|null $batch, bool $sequence = null): mixed
    {
        // If rows/fields wanted as return.
        if ($return) {
            return $batch ? $result->rows()
                 : $result->cols(0, $return === true ? '*' : $return);
        }

        // If sequence isn't false return id/ids (@default=true).
        if ($sequence !== false) {
            return $batch ? $result->ids() : $result->id();
        }

        return $result->count();
    }

    /**
     * Co-operating method for increase/decrease methods.
     */
    private function doIncreaseDecrease(string $method, string $table, string|array $field, int|float $value,
        string|array|null $where, array|null $params, array|null $options): mixed
    {
        $return = $fetch = $batch = $limit = null;
        if ($options) {
            [$return, $fetch, $batch, $limit] = array_select(
                $options, ['return', 'fetch', 'batch', 'limit']
            );
        }

        $query = $this->initQuery($table)->{$method}($field, $value, !!$return);

        $where && $query->where(...$this->prepareWhereInput($where, $params));
        $limit && $query->limit($limit);

        $result = $query->run($fetch);

        // If rows/fields wanted as return.
        if ($return) {
            return $batch ? $result->rows()
                 : $result->cols(0, is_string($field) ? $field : array_keys($field));
        }

        return $result->count();
    }
}
