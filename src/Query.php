<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\{QueryTrait, QueryException, Database, Result};
use froq\database\sql\{Sql, Name};
use froq\database\trait\DbTrait;
use froq\pager\Pager;

/**
 * Query.
 *
 * Represents a query builder entity which mostly fulfills all building needings with descriptive methods.
 *
 * @package froq\database
 * @object  froq\database\Query
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 */
final class Query
{
    /**
     * Query & Db traits.
     * @see froq\database\QueryTrait
     * @see froq\database\trait\DbTrait
     */
    use QueryTrait, DbTrait;

    /**
     * Key, tick for last call via add().
     * @var string
     * @since 5.0
     */
    private string $key;

    /** @var array */
    private array $stack = [];

    /**
     * Constructor.
     *
     * @param froq\database\Database $db
     * @param string|null            $table
     */
    public function __construct(Database $db, string $table = null)
    {
        $this->db = $db;

        $table && $this->table($table);
    }

    /**
     * Magic - string.
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Add a "WITH" query into query stack.
     *
     * @param  string       $name
     * @param  string|Query $query
     * @param  array|null   $params
     * @param  bool         $prepare
     * @param  bool         $materialized
     * @return self
     * @since  5.0
     */
    public function with(string $name, string|Query $query, array $params = null, bool $prepare = true,
        bool $materialized = true): self
    {
        $name = $this->prepareField($name);

        if ($prepare && is_string($query)) {
            $query = $this->prepare($query, $params);
        }

        return $this->add('with', [$name, null, (string) $query, $materialized]);
    }

    /**
     * Add a "WITH RECURSIVE" query into query stack.
     *
     * @param  string       $name
     * @param  string       $fields
     * @param  string|Query $query
     * @param  array|null   $params
     * @param  bool         $prepare
     * @return self
     * @since  5.0
     */
    public function withRecursive(string $name, string $fields, string|Query $query, array $params = null,
        bool $prepare = true): self
    {
        $name   = $this->prepareField($name);
        $fields = $this->prepareFields($fields);

        if ($prepare && is_string($query)) {
            $query = $this->prepare($query, $params);
        }

        return $this->add('with', [$name, $fields, (string) $query, null]);
    }

    /**
     * Set/reset the target table.
     *
     * @param  string $table
     * @param  bool   $prepare
     * @return self
     */
    public function table(string $table, bool $prepare = true): self
    {
        $prepare && $table = $this->prepareFields($table);

        return $this->add('table', $table, false);
    }

    /**
     * Add a "FROM" query into query stack.
     *
     * @param  string|Query $from
     * @param  string|null  $as
     * @param  bool         $prepare
     * @return self
     * @throws froq\database\QueryException
     */
    public function from(string|Query $from, string $as = null, bool $prepare = true): self
    {
        if (is_string($from)) {
            $prepare && $from = $this->prepareFields($from);
        } else {
            $from = '(' . $from->toString() . ')';
        }

        if ($as != '') {
            $from .= ' AS ' . $this->prepareField($as);
        }

        return $this->add('from', $from, false);
    }

    /**
     * Add/append a "SELECT" query into query stack.
     *
     * @param  string $select
     * @param  bool   $prepare
     * @return self
     * @throws froq\database\QueryException
     */
    public function select(string $select, bool $prepare = true): self
    {
        $select = trim($select);
        $select || throw new QueryException('Empty select given');

        $prepare && $select = $this->prepareFields($select);

        return $this->add('select', $select);
    }

    /**
     * Add/append a "SELECT" query into query stack from a raw query.
     *
     * @param  string      $select
     * @param  string|null $as
     * @return self
     * @throws froq\database\QueryException
     */
    public function selectRaw(string $select, string $as = null): self
    {
        $select = trim($select);
        $select || throw new QueryException('Empty select given');

        $select = '(' . $select . ')';

        if ($as != '') {
            $select .= ' AS ' . $this->prepareField($as);
        }

        return $this->add('select', $select);
    }

    /**
     * Add/append a "SELECT" query into query stack from a raw query or Query instance.
     *
     * @param  string|Query $query
     * @param  string       $as
     * @return self
     * @throws froq\database\QueryException
     */
    public function selectQuery(string|Query $query, string $as): self
    {
        return $this->selectRaw((string) $query, $as);
    }

    /**
     * Add/append a "SELECT" query into query stack with a JSON function.
     *
     * @param  array  $fields
     * @param  string $as
     * @return self
     * @throws froq\database\QueryException
     */
    public function selectJson(array $fields, string $as): self
    {
        $select = null;
        $selectSet = isset($fields[0]); // Simple check for set/map array.

        $func = match ($this->db->link()->pdoDriver()) {
            'pgsql' => $selectSet ? 'json_build_array' : 'json_build_object',
            'mysql' => $selectSet ? 'json_array'       : 'json_object',
            default => throw new QueryException('Method %s() available for PgSQL & MySQL only', __method__)
        };

        if ($selectSet) {
            $select = $this->prepareFields(join(', ', $fields));
        } else {
            foreach ($fields as $fieldKey => $fieldName) {
                $select[] = sprintf("'%s', %s", $fieldKey, $this->prepareField((string) $fieldName));
            }
            $select = join(', ', $select);
        }

        $select = trim((string) $select);
        $select || throw new QueryException('Empty select fields given');

        return $this->select($func . '(' . $select . ') AS ' . $this->prepareField($as), false);
    }

    /**
     * Add/append a "SELECT ..agg()" query into query stack.
     *
     * @alias of aggregate()
     * @since 4.14
     */
    public function selectAgg(...$args): self
    {
        return $this->aggregate(...$args);
    }

    /**
     * Add/append a "SELECT count(..)" query into query stack.
     *
     * @alias of aggregate(), for count()
     * @since 4.14
     */
    public function selectCount(...$args): self
    {
        return $this->aggregate('count', ...$args);
    }

    /**
     * Add/append a "SELECT min(..)" query into query stack.
     *
     * @alias of aggregate(), for min()
     * @since 4.4
     */
    public function selectMin(...$args): self
    {
        return $this->aggregate('min', ...$args);
    }

    /**
     * Add/append "SELECT max(..)" query into query stack.
     *
     * @alias of aggregate(), for max()
     * @since 4.4
     */
    public function selectMax(...$args): self
    {
        return $this->aggregate('max', ...$args);
    }

    /**
     * Add/append a "SELECT avg(..)" query into query stack.
     *
     * @alias of aggregate(), for avg()
     * @since 4.4
     */
    public function selectAvg(...$args): self
    {
        return $this->aggregate('avg', ...$args);
    }

    /**
     * Add/append a "SELECT sum(..)" query into query stack.
     *
     * @alias of aggregate(), for sum()
     * @since 4.4
     */
    public function selectSum(...$args): self
    {
        return $this->aggregate('sum', ...$args);
    }

    /**
     * Add an "INSERT" query into query stack.
     *
     * @param  array|null $data
     * @param  bool|null  $batch
     * @param  bool|null  $sequence
     * @return self
     * @throws froq\database\QueryException
     */
    public function insert(array $data = null, bool $batch = null, bool $sequence = null): self
    {
        // For with()/into() calls.
        if ($data === null) {
            return $this->add('insert', '1', false);
        }

        $fields = $values = [];

        if (!$batch) {
            // Eg: ["name" => "Kerem", ..].
            $fields = array_keys($data);
            $values = [array_values($data)];
        } else {
            // Eg: ["fields" => ["name", ..], "values" => ["Kerem", ..]].
            $fields = (array) ($data['fields'] ?? []);
            $values = (array) ($data['values'] ?? []);
        }

        if (!$fields || !$values) {
            throw new QueryException('Both fields & values must not be empty for insert');
        }

        $fieldsCount = count($fields);
        foreach ($values as $i => $value) {
            $value = (array) $value;
            if (count($value) != $fieldsCount) {
                throw new QueryException('Count of value set `%s` not matched with fields count', $i);
            }

            $values[$i] = '(' . join(', ', $this->db->escape($value)) . ')';
        }

        $fields = $this->prepareFields(join(', ', $fields));

        return $this->add('insert', [$fields, $values, 'sequence' => $sequence], false);
    }

    /**
     * Add an "UPDATE" query into query stack.
     *
     * @param  array|null $data
     * @param  bool       $escape
     * @return self
     * @throws froq\database\QueryException
     */
    public function update(array $data = null, bool $escape = true): self
    {
        // For with() calls.
        if ($data === null) {
            return $this->add('update', '1', false);
        }

        $data || throw new QueryException('Empty data given for update');

        $set = [];
        foreach ($data as $name => $value) {
            $set[] = $this->db->escapeName($name)
                . ' = ' . ($escape ? $this->db->escape($value) : $value);
        }

        return $this->add('update', $set, false);
    }

    /**
     * Add/append "DELETE" query into query stack.
     *
     * @return self
     */
    public function delete(): self
    {
        return $this->add('delete', '1', false);
    }

    /**
     * Add an "INTO" clause into query stack for inserts.
     *
     * @param  string $table
     * @return self
     * @since  5.0
     */
    public function into(string $table): self
    {
        $table = $this->prepareField($table);

        return $this->add('into', $table, false);
    }

    /**
     * Add an "SET" clause into query stack for updates.
     *
     * @param  array $data
     * @param  bool  $escape
     * @return self
     * @since  5.0
     */
    public function set(array $data, bool $escape = true)
    {
        return $this->update($data, $escape);
    }

    /**
     * Add a "RETURNING" clause into query stack.
     *
     * @param  string                    $fields
     * @param  string|array<string>|null $fetch
     * @return self
     * @since  4.18
     */
    public function return(string $fields, string|array $fetch = null): self
    {
        $fields = $this->prepareFields($fields);

        return $this->add('return', ['fields' => $fields, 'fetch' => $fetch], false);
    }

    /**
     * Add a "CONFLICT" clause into query stack.
     *
     * @param  string     $fields
     * @param  string     $action
     * @param  array|null $update
     * @param  array|null $where
     * @return self
     * @throws froq\database\QueryException
     * @since  4.18
     */
    public function conflict(string $fields, string $action, array $update = null, array $where = null): self
    {
        $action = strtoupper($action);

        if (!equals($action, 'NOTHING', 'UPDATE')) {
            throw new QueryException('Invalid action `%s` for conflict, valids are: NOTHING, UPDATE',
                $action);
        }

        $fields = $this->prepareFields($fields);

        return $this->add('conflict', ['fields' => $fields, 'action' => $action,
                                       'update' => $update, 'where'  => $where], false);
    }

    /**
     * Add/append a "JOIN" query into query stack.
     *
     * @param  string      $to
     * @param  string|null $on
     * @param  array|null  $params
     * @param  string|null $type
     * @return self
     */
    public function join(string $to, string $on = null, array $params = null, string $type = null): self
    {
        $type && $type = strtoupper($type) . ' ';
        $on   && $on   = 'ON (' . $this->prepare($on, $params) . ')';

        return $this->add('join', [$type . 'JOIN ' . $this->prepareFields($to), $on]);
    }

    /**
     * Add/append an "ON" clause into query stack.
     *
     * @param  string     $on
     * @param  array|null $params
     * @return self
     * @since  5.0
     */
    public function on(string $on, array $params = null): self
    {
        return $this->addTo('join', 'ON (' . $this->prepare($on, $params) . ')');
    }

    /**
     * Add/append an "USING" clause into query stack.
     *
     * @param  string $fields
     * @return self
     * @since  5.0
     */
    public function using(string $fields): self
    {
        return $this->addTo('join', 'USING (' . $this->prepareFields($fields) . ')');
    }

    /**
     * Add/append a "LEFT JOIN" query into query stack.
     *
     * @param  string     $to
     * @param  string     $on
     * @param  array|null $params
     * @param  bool       $outer
     * @return self
     */
    public function joinLeft(string $to, string $on, array $params = null, bool $outer = false): self
    {
        return $this->join($to, $on, $params, 'LEFT' . ($outer ? ' OUTER' : ''));
    }

    /**
     * Add/append a "RIGHT JOIN" query into query stack.
     *
     * @param  string     $to
     * @param  string     $on
     * @param  array|null $params
     * @param  bool       $outer
     * @return self
     */
    public function joinRight(string $to, string $on, array $params = null, bool $outer = false): self
    {
        return $this->join($to, $on, $params, 'RIGHT' . ($outer ? ' OUTER' : ''));
    }

    /**
     * Add/append a "WHERE" query into query stack.
     *
     * @param  string|array $where
     * @param  array|null   $params
     * @param  string|null  $op
     * @return self
     */
    public function where(string|array $where, array $params = null, string $op = null): self
    {
        $op = $this->prepareOp($op ?: 'AND'); // @default=AND

        if (is_string($where)) {
            $this->add('where', [$this->prepare($where, $params), $op]);
        } else {
            // Eg: [id => 1, status => ok, ..].
            foreach ($where as $field => $param) {
                $this->add('where', [$this->prepareField($field) . ' = ?', $param, $op]);
            }
        }

        return $this;
    }

    /**
     * Add/append a "WHERE" query into query stack for an equality condition.
     *
     * @param  string      $field
     * @param  any         $param
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereEqual(string $field, $param, string $op = null): self
    {
        if (is_array($param)) {
            return $this->whereIn($field, $param);
        }

        $param = (array) $param;
        $param || throw new QueryException('No parameter given');

        return $this->where($this->prepareField($field) . ' = ?', $param, $op);
    }

    /**
     * Add/append a "WHERE" query into query stack foor a non-equality condition.
     *
     * @param  string      $field
     * @param  any         $param
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotEqual(string $field, $param, string $op = null): self
    {
        if (is_array($param)) {
            return $this->whereNotIn($field, $param);
        }

        $param = (array) $param;
        $param || throw new QueryException('No parameter given');

        return $this->where($this->prepareField($field) . ' != ?', $param, $op);
    }

    /**
     * Add/append a "WHERE" query into query stack for a null/true/false condition.
     *
     * @param  string      $field
     * @param  bool|null   $value
     * @param  string|null $op
     * @return self
     * @since  5.0
     */
    public function whereIs(string $field, bool|null $value, string $op = null): self
    {
        $value = is_null($value) ? 'NULL' : ($value ? 'TRUE' : 'FALSE');

        return $this->where($this->prepareField($field) . ' IS ' . $value, null, $op);
    }

    /**
     * Add/append a "WHERE" query into query stack for a not null/true/false condition.
     *
     * @param  string      $field
     * @param  bool|null   $value
     * @param  string|null $op
     * @return self
     * @since  5.0
     */
    public function whereIsNot(string $field, bool|null $value, string $op = null): self
    {
        $value = is_null($value) ? 'NULL' : ($value ? 'TRUE' : 'FALSE');

        return $this->where($this->prepareField($field) . ' IS NOT ' . $value, null, $op);
    }

    /**
     * Add/append a "WHERE .. IN (..)" query into query stack.
     *
     * @param  string      $field
     * @param  array       $params
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereIn(string $field, array $params, string $op = null): self
    {
        $params || throw new QueryException('No parameters given');

        return $this->where($this->prepareField($field)
            . ' IN (' . $this->prepareWhereInPlaceholders($params) . ')', $params, $op);
    }

    /**
     * Add/append a "WHERE .. NOT IN (..)" query into query stack.
     *
     * @param  string      $field
     * @param  array       $params
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotIn(string $field, array $params, string $op = null): self
    {
        $params || throw new QueryException('No parameters given');

        return $this->where($this->prepareField($field)
            . ' NOT IN (' . $this->prepareWhereInPlaceholders($params) . ')', $params, $op);
    }

    /**
     * Add/append a "WHERE .. NULL" query into query stack.
     *
     * @param  string      $field
     * @param  string|null $op
     * @return self
     */
    public function whereNull(string $field, string $op = null): self
    {
        return $this->whereIs($field, null, $op);
    }

    /**
     * Add/append a "WHERE .. NOT NULL" query into query stack.
     *
     * @param  string      $field
     * @param  string|null $op
     * @return self
     */
    public function whereNotNull(string $field, string $op = null): self
    {
        return $this->whereIsNot($field, null, $op);
    }

    /**
     * Add/append "WHERE .. BETWEEN .." query into query stack.
     *
     * @param  string      $field
     * @param  array       $params
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereBetween(string $field, array $params, string $op = null): self
    {
        $params || throw new QueryException('No parameters given');

        return $this->where($this->prepareField($field) . ' BETWEEN ? AND ?', $params, $op);
    }

    /**
     * Add/append "WHERE NOT .. BETWEEN .." query into query stack.
     *
     * @param  string      $field
     * @param  array       $params
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotBetween(string $field, array $params, string $op = null): self
    {
        $params || throw new QueryException('No parameters given');

        return $this->where($this->prepareField($field) . ' NOT BETWEEN ? AND ?', $params, $op);
    }

    /**
     * Add/append a "WHERE .. < .." query into query stack.
     *
     * @param  string           $field
     * @param  string|int|float $param
     * @param  string|null      $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereLessThan(string $field, string|int|float $param, string $op = null): self
    {
        $param = (array) $param;
        $param || throw new QueryException('No parameter given');

        return $this->where($this->prepareField($field) . ' < ?', $param, $op);
    }

    /**
     * Add/append a "WHERE .. <= .." query into query stack.
     *
     * @param  string           $field
     * @param  string|int|float $param
     * @param  string|null      $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereLessThanEqual(string $field, string|int|float $param, string $op = null): self
    {
        $param = (array) $param;
        $param || throw new QueryException('No parameter given');

        return $this->where($this->prepareField($field) . ' <= ?', $param, $op);
    }

    /**
     * Add/append a "WHERE .. > .." query into query stack.
     *
     * @param  string           $field
     * @param  string|int|float $param
     * @param  string|null      $op
     * @return self
     */
    public function whereGreaterThan(string $field, string|int|float $param, string $op = null): self
    {
        $param = (array) $param;
        $param || throw new QueryException('No parameter given');

        return $this->where($this->prepareField($field) . ' > ?', $param, $op);
    }

    /**
     * Add/append a "WHERE .. >= .." query into query stack.
     *
     * @param  string           $field
     * @param  string|int|float $param
     * @param  string|null      $op
     * @return self
     */
    public function whereGreaterThanEqual(string $field, string|int|float $param, string $op = null): self
    {
        $param = (array) $param;
        $param || throw new QueryException('No parameter given');

        return $this->where($this->prepareField($field) . ' >= ?', $param, $op);
    }

    /**
     * Add/append a "WHERE .. LIKE/ILIKE .." query into query stack.
     *
     * @param  string       $field
     * @param  string|array $params
     * @param  bool         $ilike
     * @param  string|null  $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereLike(string $field, string|array $params, bool $ilike = false, string $op = null): self
    {
        $params = (array) $params;
        $params || throw new QueryException('No parameters given');

        [$field, $search] = [$this->prepareField($field), $this->prepareWhereLikeSearch($params)];

        if (!$ilike) {
            $where = $field . ' LIKE ' . $search;
        } else {
            $where = ($this->db->link()->pdoDriver() == 'pgsql')
                ? sprintf('%s ILIKE %s', $field, $search)
                : sprintf('lower(%s) LIKE lower(%s)', $field, $search);
        }

        return $this->where($where, null, $op);
    }

    /**
     * Add/append a "WHERE .. NOT LIKE/NOT ILIKE .." query into query stack.
     *
     * @param  string       $field
     * @param  string|array $params
     * @param  bool         $ilike
     * @param  string|null  $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotLike(string $field, string|array $params, bool $ilike = false, string $op = null): self
    {
        $params = (array) $params;
        $param || throw new QueryException('No parameters given');

        [$field, $search] = [$this->prepareField($field), $this->prepareWhereLikeSearch($params)];

        if (!$ilike) {
            $where = $field . ' NOT LIKE ' . $search;
        } else {
            $where = ($this->db->link()->pdoDriver() == 'pgsql')
                ? sprintf('%s NOT ILIKE %s', $field, $search)
                : sprintf('lower(%s) NOT LIKE lower(%s)', $field, $search);
        }

        return $this->where($where, null, $op);
    }

    /**
     * Add/append a "WHERE EXISTS (..)" query into query stack.
     *
     * @param  string|Query $query
     * @param  array|null   $params
     * @param  string|null  $op
     * @return self
     */
    public function whereExists(string|Query $query, array $params = null, string $op = null): self
    {
        if ($params && is_string($query)) {
            $query = $this->prepare($query, $params);
        }

        return $this->where('EXISTS (' . $query . ')', null, $op);
    }

    /**
     * Add/append a "WHERE NOT EXISTS (..)" query into query stack.
     *
     * @param  string|Query $query
     * @param  array|null   $params
     * @param  string       $op
     * @return self
     */
    public function whereNotExists(string|Query $query, array $params = null, string $op = null): self
    {
        if ($params && is_string($query)) {
            $query = $this->prepare($query, $params);
        }

        return $this->where('NOT EXISTS (' . $query . ')', null, $op);
    }

    /**
     * Add/append a "WHERE random()/rand()" query into query stack.
     *
     * @param  float       $value
     * @param  string|null $op
     * @return self
     */
    public function whereRandom(float $value = 0.01, string $op = null): self
    {
        return ($this->db->link()->pdoDriver() == 'pgsql')
            ? $this->where('random() < ' . $value, $op)
            : $this->where('rand() < ' . $value, $op);
    }

    /**
     * Add/append a "HAVING" clause into query stack.
     *
     * @param  string|Query $query
     * @param  array|null $params
     * @return self
     */
    public function having(string|Query $query, array $params = null): self
    {
        if ($params && is_string($query)) {
            $query = $this->prepare($query, $params);
        }

        return $this->add('having', (string) $query, false);
    }

    /**
     * Add/append a "GROUP BY .." clause into query stack.
     *
     * @param  string      $field
     * @param  string|bool $rollup
     * @return self
     */
    public function groupBy(string $field, string|bool $rollup = null): self
    {
        $field = $this->prepareFields($field);

        if ($rollup != null) {
            $field .= ($this->db->link()->pdoDriver() == 'mysql') ? ' WITH ROLLUP' : ' ROLLUP (' . (
                is_string($rollup) ? $this->prepareFields($rollup) : $field
            ) . ')';
        }

        return $this->add('group', $field);
    }

    /**
     * Add/append an "ORDER BY .." clause into query stack.
     *
     * @param  string|Sql      $field
     * @param  string|int|null $op
     * @param  array|null      $options
     * @return self
     * @throws froq\database\QueryException
     */
    public function orderBy(string|Sql $field, string|int $op = null, array $options = null): self
    {
        $isSql = $field instanceof Sql;

        $field = trim((string) $field);
        $field || throw new QueryException('No field given');

        // Eg: ("id", "ASC") or ("id", 1) or ("id", -1).
        if ($op != null) {
            $field .= ' ' . $this->prepareOp(strval($op), true);
        }

        // Extract options (with defaults).
        [$collate, $nulls] = [
            $options['collate'] ?? null,
            $options['nulls']   ?? null,
        ];

        // Eg: "tr_TR" or "tr_TR.utf8".
        if ($collate != null) {
            $collate = ' COLLATE ' . $this->prepareCollation($collate);
        }

        // Eg: "FIRST" or "LAST".
        if ($nulls != null) {
            $nulls = ' NULLS ' . strtoupper($nulls);
        }

        // For raw Sql fields.
        if ($isSql) {
            return $this->add('order', $field . $collate . $nulls);
        }

        // Eg: ("id ASC") or ("id ASC, name DESC").
        if (strpos($field, ' ')) {
            $fields = [];
            foreach (split(',', $field) as $i => $field) {
                [$field, $op] = split(' ', trim($field), 2);
                $fields[$i] = $this->prepareField($field) . $collate;
                if ($op != null) {
                    $fields[$i] .= ' ' . $this->prepareOp($op, true);
                }
            }

            return $this->add('order', join(', ', $fields) . $nulls);
        }

        return $this->add('order', $this->prepareField($field) . $collate . $nulls);
    }

    /**
     * Add/append an "ORDER BY random()/rand()" clause into query stack.
     *
     * @return self
     */
    public function orderByRandom(): self
    {
        return ($this->db->link()->pdoDriver() == 'pgsql')
            ? $this->add('order', 'random()') : $this->add('order', 'rand()');
    }

    /**
     * Add "LIMIT" clause into query stack.
     *
     * @param  int      $limit
     * @param  int|null $offset
     * @return self
     */
    public function limit(int $limit, int $offset = null): self
    {
        return ($offset === null) ? $this->add('limit', abs($limit), false)
            : $this->add('limit', abs($limit), false)->add('offset', abs($offset), false);
    }

    /**
     * Add "OFFSET" clause into query stack.
     *
     * @param  int $offset
     * @return self
     * @throws froq\database\QueryException
     */
    public function offset(int $offset): self
    {
        if ($this->has('limit')) {
            return $this->add('offset', abs($offset), false);
        }

        throw new QueryException('Limit not set yet, call limit() first');
    }

    /**
     * Add/append an "AS" operator into query stack for a table or field.
     *
     * @param  string $as
     * @param  bool   $prepare
     * @return self
     * @throws froq\database\QueryException
     * @since  4.16, 5.0 Optimized for table statement.
     */
    public function as(string $as, bool $prepare = true): self
    {
        if (empty($this->key)) {
            throw new QueryException('No table/select statement yet in query stack to apply AS operator,'
                . ' call one of them first to apply');
        }

        $prepare && $as = $this->prepareField($as);

        match ($this->key) {
            'table'  => strpos($this->stack['table'], ' AS ')
                            || $this->stack['table'] .= ' AS ' . $as, // Concat.
            'select' => strpos($this->stack['select'][count($this->stack['select']) - 1], ' AS ')
                            || $this->stack['select'][count($this->stack['select']) - 1] .= ' AS ' . $as,
            default  => throw new QueryException('Invalid key `%s` for as()', $this->key)
        };

        return $this;
    }

    /**
     * Set last where query operator to "OR".
     *
     * @return self
     * @throws froq\database\QueryException
     */
    public function or(): self
    {
        return $this->addTo('where', 'OR');
    }

    /**
     * Set last where query operator to "AND".
     *
     * @return self
     * @throws froq\database\QueryException
     */
    public function and(): self
    {
        return $this->addTo('where', 'AND');
    }

    /**
     * Shortcut for orderBy() for "ASC" directive with default "id" field.
     *
     * @param  string     $field
     * @param  array|null $options
     * @return self
     */
    public function asc(string $field = 'id', string $options = null): self
    {
        return $this->orderBy($field, 'ASC', $options);
    }

    /**
     * Shortcut for orderBy() for "DESC" directive with default "id" field.
     *
     * @param  string     $field
     * @param  array|null $options
     * @return self
     */
    public function desc(string $field = 'id', string $options = null): self
    {
        return $this->orderBy($field, 'DESC', $options);
    }

    /**
     * Shortcut for whereEqual() with "id" field.
     *
     * @param  int|string $id
     * @return self
     */
    public function id(int|string $id): self
    {
        return $this->whereEqual('id', $id);
    }

    /**
     * Run a query stringifying current query stack.
     *
     * @param  string|array<string>|null $fetch
     * @param  bool|null                 $sequence
     * @return froq\database\Result
     */
    public function run(string|array $fetch = null, bool $sequence = null): Result
    {
        // Get from stack if given with return() / insert().
        $fetch    ??= $this->stack['return']['fetch']    ?? null;
        $sequence ??= $this->stack['insert']['sequence'] ?? null;

        return $this->db->query($this->toString(), null, ['fetch' => $fetch, 'sequence' => $sequence]);
    }

    /**
     * Execute a query stringifying current query stack.

     * @return int|null
     * @since  4.3
     */
    public function runExec(): int|null
    {
        return $this->db->execute($this->toString());
    }

    /**
     * Get a result row stringifying & running current query stack.
     *
     * @param  string|array<string>|null $fetch
     * @return array|object|null
     */
    public function get(string|array $fetch = null): array|object|null
    {
        // Optimize one-record query.
        $this->has('limit') || $this->limit(1);

        return $this->db->get($this->toString(), null, $fetch);
    }

    /**
     * Get all result rows stringifying & running current query stack.
     *
     * @param  string|array<string>|null  $fetch
     * @param  froq\pager\Pager|null     &$pager
     * @param  int|null                   $limit
     * @return array|null
     */
    public function getAll(string|array $fetch = null, Pager &$pager = null, int $limit = null): array|null
    {
        if ($limit === null) {
            return $this->db->getAll($this->toString(), null, $fetch);
        }

        $this->paginate($pager, $limit);

        return $this->db->getAll($this->toString(), null, $fetch);
    }

    /**
     * Get a result row as array stringifying & running current query stack.
     *
     * @return array|null
     * @since  4.7
     */
    public function getArray(): array|null
    {
        return $this->get('array');
    }

    /**
     * Get a result row as object stringifying & running current query stack.
     *
     * @return object|null
     * @since  4.7
     */
    public function getObject(): object|null
    {
        return $this->get('object');
    }

    /**
     * Get all result rows as array stringifying & running current query stack.
     *
     * @param  froq\pager\Pager|null &$pager
     * @param  int|null               $limit
     * @return array|null
     * @since  4.7
     */
    public function getArrayAll(Pager &$pager = null, int $limit = null): array|null
    {
        return $this->getAll('array', $pager, $limit);
    }

    /**
     * Get all result rows as object stringifying & running current query stack.
     *
     * @param  froq\pager\Pager|null &$pager
     * @param  int|null               $limit
     * @return array|null
     * @since  4.7
     */
    public function getObjectAll(Pager &$pager = null, int $limit = null): array|null
    {
        return $this->getAll('object', $pager, $limit);
    }

    /**
     * Get count result stringifying & running current query stack.
     *
     * @return int
     */
    public function count(): int
    {
        // Prevent empty query exception.
        $this->has('select') || $this->add('select', '1');

        return $this->db->countQuery($this->toString());
    }

    /**
     * Add/append a "SELECT" query into query stack for an aggregate function.
     *
     * @param  string      $func
     * @param  string      $field
     * @param  string|null $as
     * @param  array|null  $options
     * @return self
     * @throws froq\database\QueryException
     * @since  4.4
     */
    public function aggregate(string $func, string $field, string $as = null, array $options = null): self
    {
        // Extract options (with defaults).
        [$distinct, $prepare, $order] = [
            $options['distinct'] ?? false,
            $options['prepare']  ?? true,
            $options['order']    ?? null,
        ];

        $distinct && $distinct = 'DISTINCT ';
        $prepare  && $field    = $this->prepareField($field);

        // Dirty hijack..
        if ($order != null) {
            $order = current((clone $this)->reset()->orderBy($order)->stack['order']);
            $order = ' ORDER BY ' . $order;
        }

        if ($as != '') {
            $as = ' AS ' . $this->prepareField($as);
        }

        // Base functions.
        if (in_array($func, ['count', 'sum', 'min', 'max', 'avg'])) {
            return $this->select($func . '(' . $distinct . $field . $order . ')' . $as, false);
        }

        // PostgreSQL functions (no "_agg" suffix needed).
        if (in_array($func, ['array', 'string', 'json', 'json_object', 'jsonb', 'jsonb_object'])) {
            return $this->select($func . '_agg(' . $distinct . $field . $order . ')' . $as, false);
        }

        throw new QueryException('Invalid aggregate function `%s`, valids are: count, sum, min, max, avg,'
            . ' array, string, json, json_object, jsonb, jsonb_object', [$func]);
    }

    /**
     * Paginate query result setting ref'ed pager instance.
     *
     * @param  froq\pager\Pager|null &$pager
     * @param  int|null               $limit
     * @return self
     */
    public function paginate(Pager &$pager = null, int $limit = null): self
    {
        $pager = $pager ?? $this->db->initPager($this->count(), $limit);

        return $this->paginateWith($pager);
    }

    /**
     * Paginate query result with given pager instance.
     *
     * @param  froq\pager\Pager $pager
     * @return self
     */
    public function paginateWith(Pager $pager): self
    {
        return $this->limit($pager->getLimit(), $pager->getOffset());
    }

    /**
     * Create an Sql instance for a raw query/clause/statement.
     *
     * @param  string     $in
     * @param  array|null $params
     * @return froq\database\sql\Sql
     */
    public function sql(string $in, array $params = null): Sql
    {
        return new Sql($this->prepare($in, $params));
    }

    /**
     * Create a Name instance for an identifier (table, field etc).
     *
     * @param  string $in
     * @return froq\database\sql\Name
     */
    public function name(string $in): Name
    {
        return new Name($in);
    }

    /**
     * Append a raw or prepared query to current query stack, when needed with a with() query etc.
     *
     * @param  string|Query|null $query
     * @param  int|null          $indent
     * @return self
     * @since  5.0
     */
    public function affix(string|Query $query = null, int $indent = null): self
    {
        $query ??= $this;

        if ($query instanceof Query) { // Get & clean behind.
            [$query] = [$this->toString($indent, false), $query->reset()];
        }

        return $this->add('affix', $query);
    }

    /**
     * Reset current query stack.
     *
     * @return self
     */
    public function reset(): self
    {
        $this->stack = [];
        $this->key = '';

        return $this;
    }

    /**
     * Get query stack.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->stack;
    }

    /**
     * Get query stack as string.
     *
     * @param  int|null $indent
     * @param  bool     $trim @internal
     * @return string
     * @throws froq\database\QueryException
     */
    public function toString(int $indent = null, bool $trim = true): string
    {
        $n = $t = ' ';
        if ($indent) {
            $n = "\n"; $t = "\t";
        }

        $ret = '';

        if ($this->has('with')) {
            $ret = $this->toQueryString('with', $indent);
        }

        if ($this->has('affix')) {
            $ret .= $n . join($n . $t, $this->stack['affix']);
        }

        foreach (['insert', 'update', 'delete', 'select'] as $key) {
            $this->has($key) && $ret .= $this->toQueryString($key, $indent);
        }

        if ($ret == '') {
            throw new QueryException('No query ready to build, use select(), insert(), update(), delete(),'
                . ' aggregate() etc. first');
        }

        $trim && $ret = trim($ret);

        return $ret;
    }

    /**
     * Get a part of query stack as string.
     *
     * @param  string   $key
     * @param  int|null $indent
     * @return string
     * @throws froq\database\QueryException
     */
    public function toQueryString(string $key, int $indent = null): string
    {
        $n = ' ';
        $nt = ' '; $ts = '';
        if ($indent) {
            if ($indent == 1) {
                $n = "\n";
                $nt = "\n"; $ts = "\t";
            } elseif ($indent > 1) {
                $n = "\n"; $t = str_repeat("\t", $indent - 1);
                $nt = "\n" . $t; $ts = str_repeat("\t", $indent - 1 + 1); // Sub.
            }
        }

        $ret = '';

        $stack = $this->stack;
        switch ($key) {
            case 'with':
                if (isset($stack['with'])) {
                    $with = [];
                    foreach ($stack['with'] as [$name, $fields, $query, $materialized]) {
                        if ($fields !== null) {
                            $as = 'RECURSIVE ' . $name . ' (' . $fields . ') AS ';
                        } else {
                            $as = $name . ' AS ' . (!$materialized ? 'NOT MATERIALIZED ' : '');
                        }

                        if ($indent >= 1) {
                            $qs = explode("\n", $query, 2); // Find first tab space.
                            $ts = str_repeat("\t", count($qs) < 2 ? 0 : strspn($qs[1], "\t"));

                            $with[] = $as . '(' . $n . $ts . $query . $nt . ')';
                        } else {
                            $with[] = $as . '(' . $query . ')';
                        }
                    }

                    $ret = $nt . 'WITH ' . join(', ', $with);
                }
                break;
            case 'select':
                if (isset($stack['select'])) {
                    $table = $stack['from'] ?? $stack['table'] ?? null;
                    $table || throw new QueryException('Table is not defined yet, call from() or table() to continue');

                    if ($stack['select'] == ['*']) {
                        $select = '*';
                    } else {
                        foreach ($stack['select'] as $field) {
                            $select[] = $n . $ts . $field;
                        }
                        $select = join(',', $select);
                    }

                    $ret = $nt . 'SELECT ' . trim($select)
                         . $nt . 'FROM ' . $table;

                    isset($stack['join'])   && $ret .= $nt . $this->toQueryString('join');
                    isset($stack['where'])  && $ret .= $nt . $this->toQueryString('where', $indent);
                    isset($stack['group'])  && $ret .= $nt . $this->toQueryString('group');
                    isset($stack['having']) && $ret .= $nt . $this->toQueryString('having');
                    isset($stack['order'])  && $ret .= $nt . $this->toQueryString('order');
                    isset($stack['limit'])  && $ret .= $nt . $this->toQueryString('limit');
                }
                break;
            case 'insert':
                if (isset($stack['insert'])) {
                    $table = $stack['into'] ?? $stack['table'] ?? null;
                    $table || throw new QueryException('Table is not defined yet, call into() or table() to continue');

                    if ($stack['insert'] == '1') {
                        $ret = $nt . 'INSERT INTO ' . $table;
                    } else {
                        [$fields, $values] = $stack['insert'];

                        $ret = $nt . 'INSERT INTO ' . $table
                             . $nt . '(' . $fields . ')'
                             . $nt . 'VALUES' . $nt . join(',' . $nt, $values);
                    }

                    if (isset($stack['conflict'])) {
                        ['fields' => $fields, 'action' => $action,
                         'update' => $update, 'where'  => $where] = $stack['conflict'];

                        $ret .= match ($driver = $this->db->link()->pdoDriver()) {
                            'pgsql' => $nt . 'ON CONFLICT (' . $fields . ') DO ' . $action,
                            'mysql' => $nt . 'ON DUPLICATE KEY ' . ($action = 'UPDATE'),
                            default => throw new QueryException('Method conflict() available for PgSQL & MySQL only')
                        };

                        if ($action == 'UPDATE') {
                            $temp = (clone $this)->reset()->table('@');
                            $temp->update($update);

                            $ret .= ($driver == 'pgsql')
                                  ? $nt . 'SET ' . join(', ', $temp->stack['update'])
                                  : $nt . join(', ', $temp->stack['update']);

                            if ($where != null) {
                                @ [$where, $params] = (array) $where;
                                $ret .= $nt . trim($temp->where((string) $where, (array) $params)
                                    ->toQueryString('where'));
                            }

                            unset($temp);
                        }
                    }

                    if (isset($stack['return'])) {
                        $ret .= $nt . 'RETURNING ' . $stack['return']['fields'];
                    }
                }
                break;
            case 'update':
                if (isset($stack['update'])) {
                    $table = $stack['table'] ?? null;
                    $table || throw new QueryException('Table is not defined yet, call table() to continue');

                    if (!isset($stack['where'])) {
                        throw new QueryException('No `where` for update yet, it must be provided for security'
                            . ' reasons, call at least where("1=1") proving you are aware of what is going on');
                    }

                    $ret = $nt . 'UPDATE ' . $table
                         . $nt . 'SET ' . join(', ' . $nt, $stack['update']);

                    isset($stack['where']) && $ret .= $nt . $this->toQueryString('where', $indent);
                    isset($stack['order']) && $ret .= $nt . $this->toQueryString('order');
                    isset($stack['limit']) && $ret .= $nt . $this->toQueryString('limit');

                    if (isset($stack['return'])) {
                        $ret .= $nt . 'RETURNING ' . $stack['return']['fields'];
                    }
                }
                break;
            case 'delete':
                if (isset($stack['delete'])) {
                    $table = $stack['from'] ?? $stack['table'] ?? null;
                    $table || throw new QueryException('Table is not defined yet, call from() or table() to continue');

                    if (!isset($stack['where'])) {
                        throw new QueryException('No `where` for delete yet, it must be provided for security'
                            . ' reasons, call at least where("1=1") proving you are aware of what is going on');
                    }

                    $ret = $nt . 'DELETE FROM ' . $table;

                    isset($stack['where']) && $ret .= $nt . $this->toQueryString('where', $indent);
                    isset($stack['order']) && $ret .= $nt . $this->toQueryString('order');
                    isset($stack['limit']) && $ret .= $nt . $this->toQueryString('limit');

                    if (isset($stack['return'])) {
                        $ret .= $nt . 'RETURNING ' . $stack['return']['fields'];
                    }
                }
                break;
            case 'where':
                if (isset($stack['where'])) {
                    $wheres = $stack['where'];
                    if (count($wheres) == 1) {
                        $ret = 'WHERE ' . $wheres[0][0];
                    } else {
                        $ws = ''; $wsi = 0;
                        foreach ($wheres as $i => [$where, $op]) {
                            $nx   = ($wheres[$i + 1] ?? null);
                            $nxnx = ($wheres[$i + 2] ?? null);
                            $nxop = ($nx[1] ?? '');

                            $ws .= $where;
                            if ($nx) {
                                $ws .= ' ' . $op . ' ';
                            }

                            if ($op != $nxop && $nxop && $nxnx) {
                                $ws .= '(';
                                $wsi++;
                            }
                        }

                        $ret = $ws . str_repeat(')', $wsi); // Concat & close parentheses.
                        if ($indent > 1) {
                            $ret = 'WHERE (' . $n . $ts . $ret . $nt . ')';
                        } else {
                            $ret = 'WHERE (' . $ret . ')';
                        }
                    }
                }
                break;
            case 'group':
                if (isset($stack['group'])) {
                    $ret = 'GROUP BY ' . join(', ', $stack['group']);
                }
                break;
            case 'order':
                if (isset($stack['order'])) {
                    $ret = 'ORDER BY ' . join(', ', $stack['order']);
                }
                break;
            case 'limit':
                if (isset($stack['limit'])) {
                    $ret = isset($stack['offset'])
                         ? 'LIMIT ' . $stack['limit'] . ' OFFSET ' . $stack['offset']
                         : 'LIMIT ' . $stack['limit'];
                }
                break;
            case 'join':
                if (isset($stack['join'])) {
                    foreach ($stack['join'] as $join) {
                        @ [$content, $context] = $join;
                        if (!$context) {
                            throw new QueryException('No join context yet, use 2. argument of join() or call'
                                . ' on()/using() method');
                        }
                        $ret .= $content . ' ' . $context;
                    }
                }
                break;
            case 'having':
                if (isset($stack['having'])) {
                    $ret = 'HAVING (' . $stack['having'] . ')';
                }
                break;
        }

        return $ret;
    }

    /**
     * Prepare an input.
     *
     * @param  string     $in
     * @param  array|null $params
     * @return string
     * @throws froq\database\QueryException
     */
    public function prepare(string $in, array $params = null): string
    {
        $out = trim($in);

        if ($out === '') {
            throw new QueryException('Empty input given');
        }

        return $params ? $this->db->prepare($out, $params) : $out;
    }

    /**
     * Prepare a field.
     *
     * @param  string $field
     * @return string
     * @throws froq\database\QueryException
     */
    public function prepareField(string $field): string
    {
        $field = trim($field);

        if ($field === '') {
            throw new QueryException('Empty field given');
        }

        return $this->db->escapeName($field);
    }

    /**
     * Prepare fields.
     *
     * @param  string $fields
     * @return string
     * @throws froq\database\QueryException
     */
    public function prepareFields(string $fields): string
    {
        $fields = trim($fields);

        if ($fields === '') {
            throw new QueryException('Empty fields given');
        }

        return strpbrk($fields, ', ') ? $this->db->escapeNames($fields)
                                      : $this->db->escapeName($fields);
    }

    /**
     * Prepare an operator.
     *
     * @param  string $op
     * @param  bool   $numerics
     * @return string
     * @throws froq\database\QueryException
     */
    public function prepareOp(string $op, bool $numerics = false): string
    {
        static $ops = ['OR', 'AND', 'ASC', 'DESC'];

        $op = strtoupper(trim($op));
        if (in_array($op, $ops)) {
            return $op;
        } elseif ($numerics && in_array($op, ['1', '-1'])) {
            return ($op == '1') ? 'ASC' : 'DESC';
        }

        throw new QueryException('Invalid op `%s`, valids are: %s, 1, -1', [$op, join(', ', $ops)]);
    }

    /**
     * Prepare collation.
     *
     * @param  string $collation
     * @return string
     */
    public function prepareCollation(string $collation): string
    {
        $collation = trim($collation);

        if ($this->db->link()->pdoDriver() == 'pgsql') {
            $collation = '"' . trim($collation, '"') . '"';
        }

        return $collation;
    }

    /**
     * Prepare where-in placeholders.
     *
     * @param  array $params
     * @return string
     */
    private function prepareWhereInPlaceholders(array $params): string
    {
        return join(', ', array_fill(0, count($params), '?'));
    }

    /**
     * Prepare where-like search.
     *
     * @param  array $params
     * @return string
     * @throws froq\database\QueryException
     */
    private function prepareWhereLikeSearch(array $params): string
    {
        $count = count($params);

        if ($count == 1) {
            return $this->db->escapeLikeString($params[0]);
        }
        if ($count < 3) {
            throw new QueryException('Like parameters count must be 1 or 3, %s given', $count);
        }

        // Note to me..
        // 'foo%'  Anything starts with "foo"
        // '%foo'  Anything ends with "foo"
        // '%foo%' Anything have "foo" in any position
        // 'f_o%'  Anything have "o" in the second position
        // 'f_%_%' Anything starts with "f" and are at least 3 characters in length
        // 'f%o'   Anything starts with "f" and ends with "o"

        [$end, $search, $start] = $params;

        $search = $this->db->escapeLikeString($search, false);
        $search = $this->db->quote($start . $search . $end);

        return $search;
    }

    /**
     * Check whether a clause/statement in query stack.
     *
     * @param  string $key
     * @return bool
     */
    private function has(string $key): bool
    {
        return isset($this->stack[$key]);
    }

    /**
     * Add a clause/statement to query stack.
     *
     * @param  string       $key
     * @param  string|array $value
     * @param  bool         $merge
     * @return self
     */
    private function add(string $key, $value, bool $merge = true): self
    {
        $merge && $value = [...($this->stack[$key] ?? []), $value];

        $this->key = $key; // Tick for last call.
        $this->stack[$key] = $value;

        return $this;
    }

    /**
     * Add a clause/statement operator to query stack.
     *
     * @param  string $key
     * @param  string $value
     * @return self
     * @throws froq\database\QueryException
     * @since  5.0
     */
    private function addTo(string $key, string $value): self
    {
        if (!isset($this->stack[$key])) {
            $op = substr(trim($value), 0, strpos(trim($value), ' '));
            throw new QueryException('No `%s` statement yet in query stack to apply `%s` operator, call'
                . ' %s() first to apply', [$key, $op, $key]);
        }

        $this->stack[$key][count($this->stack[$key]) - 1][1] = $value;

        return $this;
    }
}
