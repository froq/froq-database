<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\sql\{Sql, Name};
use froq\common\trait\FactoryTrait;
use froq\{pager\Pager, logger\Logger};
use PDO, PDOStatement, PDOException, Throwable;

/**
 * Database.
 *
 * A database worker class, contains some util methods for such operations CRUD'ing and
 * query preparing, querying/executing SQL commands.
 *
 * @package froq\database
 * @object  froq\database\Database
 * @author  Kerem Güneş
 * @since   1.0, 4.0
 */
final class Database
{
    use FactoryTrait;

    /** @var froq\database\Link */
    private Link $link;

    /** @var froq\logger\Logger|null */
    private Logger|null $logger = null;

    /** @var froq\database\Profiler|null. */
    private Profiler|null $profiler = null;

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
            $this->logger->setOption('slowQuery', $logging['slowQuery'] ?? 0);
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
        return $this->logger ?? throw new DatabaseException(
            'Database object has no logger, be sure `logging` option is not empty'
        );
    }

    /**
     * Get profiler property or throw a `DatabaseException` if no profiler.
     *
     * @return froq\database\Profiler
     * @throws froq\database\DatabaseException
     */
    public function profiler(): Profiler
    {
        return $this->profiler ?? throw new DatabaseException(
            'Database object has no profiler, be sure `profiling` option is not empty or false'
        );
    }

    /**
     * Run an SQL query and returning its result as `Result` object, or throw a
     * `DatabaseQueryException` if any query error occurs.
     *
     * @param  string     $query
     * @param  array|null $params
     * @param  array|null $options
     * @return froq\database\Result
     * @throws froq\database\{DatabaseException|DatabaseQueryException}
     */
    public function query(string $query, array $params = null, array $options = null): Result
    {
        $query = $params ? $this->prepare($query, $params) : trim($query);
        $query || throw new DatabaseException('Empty query');

        try {
            $timeop = $this->logger?->getOption('slowQuery');
            if ($timeop) {
                $marker = Profiler::marker('slowQuery');
                Profiler::mark($marker);
            }

            $pdo          = $this->link->pdo();
            $pdoStatement = empty($this->profiler) ? $pdo->query($query)
                : $this->profiler->profileQuery($query, fn() => $pdo->query($query));

            if ($timeop) {
                $time = Profiler::unmark($marker);
                if ($time > $timeop) {
                    $this->logger->logWarn("Slow query: time {$time}, {$query}");
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
     * @return int
     * @throws froq\database\{DatabaseException|DatabaseQueryException}
     * @since  4.3
     */
    public function execute(string $query, array $params = null): int
    {
        $query = $params ? $this->prepare($query, $params) : trim($query);
        $query || throw new DatabaseException('Empty query');

        try {
            $timeop = $this->logger?->getOption('slowQuery');
            if ($timeop) {
                $marker = Profiler::marker('slowQuery');
                Profiler::mark($marker);
            }

            $pdo       = $this->link->pdo();
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
                if ($errorCode != '00000' || $errorCode != '01000') {
                    throw new PDOException('Unknown error');
                }
            }

            return $pdoResult;
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
     * @param  string|bool|null  $flat
     * @return array|object|scalar|null
     */
    public function get(string $query, array $params = null, string|array $fetch = null, string|bool $flat = null)
    {
        $result = $this->query($query, $params, ['fetch' => $fetch])
                       ->rows(0);

        // When a single column value wanted.
        if ($result && $flat) {
            $result = (array) $result;
            $result = array_select($result, is_string($flat) ? $flat : key($result));
        }

        return $result;
    }

    /**
     * Get all rows running given query as `array` or return `null` if no matches.
     *
     * @param  string            $query
     * @param  array|null        $params
     * @param  string|array|null $fetch
     * @param  string|bool|null  $flat
     * @param  string|null       $index
     * @return array|null
     */
    public function getAll(string $query, array $params = null, string|array $fetch = null, string|bool $flat = null,
        string $index = null): array|null
    {
        $result = $this->query($query, $params, ['fetch' => $fetch, 'index' => $index])
                       ->rows();

        // When a single column value wanted.
        if ($result && $flat) {
            $result = (array) $result;
            $result = array_column($result, is_string($flat) ? $flat : key($result[0]));
        }

        return $result;
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
     * @param  string|bool|null  $flat
     * @param  string|null       $op
     * @return array|object|scalar|null
     */
    public function select(string $table, string $fields = '*', string|array $where = null, array $params = null,
        string $order = null, string|array $fetch = null, string|bool $flat = null, string $op = null)
    {
        $query = $this->initQuery($table)->select($fields);

        $where && $query->where(...$this->prepareWhereInput($where, $params, $op));
        $order && $query->orderBy($order);
        $query->limit(1);

        $result = $query->run($fetch)->row(0);

        // When a single column value wanted.
        if ($result && $flat) {
            $result = (array) $result;
            $result = array_select($result, is_string($flat) ? $flat : key($result));
        }

        return $result;
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
     * @param  string|bool|null  $flat
     * @param  string|null       $op
     * @return array|null
     */
    public function selectAll(string $table, string $fields = '*', string|array $where = null, array $params = null,
        string $order = null, int|array $limit = null, string|array $fetch = null, string|bool $flat = null, string $op = null)
    {
        $query = $this->initQuery($table)->select($fields);

        $where && $query->where(...$this->prepareWhereInput($where, $params, $op));
        $order && $query->orderBy($order);
        $limit && $query->limit(...(array) $limit);

        $result = $query->run($fetch)->rows();

        // When a single column value wanted.
        if ($result && $flat) {
            $result = (array) $result;
            $result = array_column($result, is_string($flat) ? $flat : key($result[0]));
        }

        return $result;
    }

    /**
     * Insert row/rows to given table.
     *
     * @param  string $table
     * @param  array  $data
     * @param  array  $options
     * @return mixed
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
                if (is_string($return)) {
                    $resultArray = (array) $result;
                    if (isset($resultArray[$return])) {
                        $result = $resultArray[$return];
                    }
                }
            }

            return $result;
        }

        if ($sequence !== false) {
            return $batch ? $result->ids() : $result->id();
        }

        return $result->count();
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
    public function update(string $table, array $data, string|array $where = null, array $params = null, array $options = null,
        string $op = null): mixed
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

        // If rows wanted as return.
        if ($return) {
            if ($batch) {
                $result = $result->rows();
            } else {
                // If single row wanted as return.
                $result = $result->row(0);
                if (is_string($return)) {
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

        // If rows wanted as return.
        if ($return) {
            if ($batch) {
                $result = $result->rows();
            } else {
                // If single row wanted as return.
                $result = $result->row(0);
                if (is_string($return)) {
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
        $return = $fetch = $batch = $limit = null;
        if ($options) {
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
     * @param  int|float  $value
     * @param  array|null $where
     * @param  array|null $params
     * @param  array|null $options
     * @return mixed
     */
    public function decrease(string $table, string|array $field, int|float $value = 1, string|array $where = null,
        array $params = null, array $options = null): mixed
    {
        $return = $fetch = $batch = $limit = null;
        if ($options) {
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
     * @return mixed
     * @throws Throwable
     */
    public function transaction(callable $call = null, callable $callError = null): mixed
    {
        $transaction = new Transaction($this->link->pdo());

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
        return "'" . $input . "'";
    }

    /**
     * Quote a name input.
     *
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
            $rpos = strpos($input, ')'); // Not parsed array[(foo, ..)] stuff, sorry.
            $rpos || throw new DatabaseException('Unclosed parenthesis in `%s` input', $input);

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

        $driver = $this->link->driver();
        if ($driver == 'pgsql') {
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

                return (strtolower($name) == 'array')
                     ? $name . '[' . $this->quoteNames($rest) . $last
                     : $this->quoteName($name) . '[' . $rest . $last;
            }
        }

        return match ($driver) {
            'mysql' => '`' . trim($input, '`')  . '`',
            'mssql' => '[' . trim($input, '[]') . ']',
            default => '"' . trim($input, '"')  . '"'
        };
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
        if (is_array($input) && $format != '?a') {
            return array_map(fn($input) => $this->escape($input, $format), $input);
        }

        if (is_object($input)) {
            return match (true) {
                ($input instanceof Sql)   => $input->content(),
                ($input instanceof Name)  => $this->escapeName($input->content()),
                ($input instanceof Query) => '(' . $input->toString() . ')',

                default => throw new DatabaseException(
                    'Invalid input object `%s` [valids: Query, sql\{Sql, Name}]',
                    $input::class
                )
            };
        }

        // Available formats: ?, ??, ?s, ?i, ?f, ?b, ?r, ?n, ?a.
        if ($format) {
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
                    'Unimplemented input format `%s`', $format
                )
            };
        }

        $type = get_type($input);

        return match ($type) {
            'null'         => 'NULL',
            'string'       => $this->escapeString($input),
            'int', 'float' => $input,
            'bool'         => $input ? 'true' : 'false',

            default => throw new DatabaseException(
                'Unimplemented input type `%s`', $type
            )
        };
    }

    /**
     * Escape a string input.
     *
     * @param  string $input
     * @param  bool   $quote
     * @param  string $extra
     * @return string
     */
    public function escapeString(string $input, bool $quote = true, string $extra = ''): string
    {
        $input = $this->link->pdo()->quote($input);

        $quote || $input = trim($input, "'");
        $extra && $input = addcslashes($input, $extra);

        return $input;
    }

    /**
     * Escape like string input.
     *
     * @param  string $input
     * @param  bool   $quote
     * @return string
     */
    public function escapeLikeString(string $input, bool $quote = true): string
    {
        return $this->escapeString($input, $quote, '%_');
    }

    /**
     * Escape a name input.
     *
     * @param  string $input
     * @return string
     */
    public function escapeName(string $input): string
    {
        $input = match ($this->link->driver()) {
            'mysql' => str_replace('`', '``', $input),
            'mssql' => str_replace(']', ']]', $input),
            default => str_replace('"', '""', $input)
        };

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
                            'Replacement `%s` not found in given parameters', $ipos
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
                $pos = strpos($holder, ':');
                // Named.
                if ($pos !== false) {
                    $key = trim($holder, ':');
                    if (!array_key_exists($key, $params)) {
                        throw new DatabaseException(
                            'Replacement key `%s` not found in given parameters', $key
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
                            'Replacement index `%s` not found in given parameters', $i
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
     * Prepare statement input returning a `PDOStatement` object.
     *
     * @param  string $input
     * @return PDOStatement
     * @throws froq\database\DatabaseException
     */
    public function prepareStatement(string $input): PDOStatement
    {
        $input = $this->prepareNameInput($input);
        $input || throw new DatabaseException('Empty input');

        try {
            return $this->link->pdo()->prepare($input);
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }
    }

    /**
     * Init a `Sql` object with/without given params.
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
     * Init a `Pager` object.
     *
     * @param  int|null   $count
     * @param  array|null $attributes
     * @return froq\pager\Pager
     */
    public function initPager(int $count = null, array $attributes = null): Pager
    {
        $pager = new Pager($attributes);
        $pager->run($count);

        return $pager;
    }

    /**
     * Prepare a prepare input escaping names only (eg: @id => "id").
     *
     * @param  string $input
     * @return string
     */
    private function prepareNameInput(string $input): string
    {
        $input = trim($input);

        if ($input != '') {
            // Prepare names (eg: '@id = ?', 1 or '@[id, ..]').
            $pos = strpos($input, '@');
            if ($pos !== false) {
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
     * Prepare a where input.
     *
     * @param  string|array $input
     * @param  array|null   $params
     * @param  string|null  $op
     * @return array
     * @since  4.15
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
                        $sign  = sprintf(' %s ', ($field[-1] == '!') ? '!=' : $field[-1]);
                        $field = substr($field, 0, -1);
                    }

                    // $type = '';
                    // if (str_contains($field, '::')) {
                    //     [$field, $type] = explode('::', $field);
                    // }

                    // ctype_alnum($field) || throw new DatabaseException(
                    //     'Invalid field name `%s` in where input, use an alphanumeric name', $field
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
}
