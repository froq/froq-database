<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\{trait\DbTrait, sql\Sql, sql\Name};
use froq\collection\Collection;
use froq\pager\Pager;

/**
 * Query.
 *
 * A query (builder) class, fulfills all building needings with descriptive methods.
 *
 * @package froq\database
 * @object  froq\database\Query
 * @author  Kerem Güneş
 * @since   4.0
 */
final class Query
{
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

    /** @magic */
    public function __toString(): string
    {
        return $this->toString();
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
     * @param  string|array|Query $select
     * @param  bool               $prepare
     * @param  bool               $wrap
     * @param  string|null        $as
     * @return self
     * @throws froq\database\QueryException
     */
    public function select(string|array|Query $select = '*', bool $prepare = true, bool $wrap = false, string $as = null): self
    {
        if ($select instanceof Query) {
            $select = $select->toString(); $wrap = true;
        } else {
            if (is_array($select)) {
                $select = join(', ', $select);
            }

            $select = trim($select);
            $select || throw new QueryException('Empty select given');

            if ($prepare && $select != '*') {
                $select = $this->prepareFields($select);
            }
        }

        $select = $wrap ? '(' . $select . ')' : $select;

        if ($as != '') {
            $select .= ' AS ' . $this->prepareField($as);
        }

        return $this->add('select', $select);
    }

    /**
     * Add/append a "SELECT" query into query stack from a raw query or Query instance.
     *
     * @param  string|Query $query
     * @param  array|null   $params
     * @param  string|null  $as
     * @param  bool         $wrap
     * @return self
     * @causes froq\database\QueryException
     */
    public function selectQuery(string|Query $query, array $params = null, string $as = null, bool $wrap = true): self
    {
        if (is_string($query)) {
            $query = $this->prepare($query, $params);
        }

        return $this->select($query, false, $wrap, $as);
    }

    /**
     * Add/append a "SELECT" query into query stack with a JSON function.
     *
     * @param  string|array<string> $fields
     * @param  string|null          $as
     * @param  bool                 $prepare
     * @return self
     * @throws froq\database\QueryException
     */
    public function selectJson(string|array $fields, string $as = null, bool $prepare = true): self
    {
        // Eg: ('id:foo.id, ..').
        if (is_string($fields)) {
            $parts  = split('\s*,\s*', $fields);
            $fields = [];

            foreach ($parts as $part) {
                [$key, $name] = split('\s*:\s*', $part, 2);
                $fields[$key] = $name;
            }

            unset($parts, $part);
        }

        $list = is_list($fields);

        $func = match ($this->db->link()->driver()) {
            'pgsql' => $list ? 'json_build_array' : 'json_build_object',
            'mysql' => $list ? 'json_array'       : 'json_object',
            default => throw new QueryException('Method selectJson() available for PgSQL & MySQL only')
        };

        if ($list) {
            $select = $this->prepareFields($fields);
        } else {
            foreach ($fields as $key => $field) {
                if ($field instanceof Query || $field instanceof Sql) {
                    $field = '(' . $field . ')'; // For raw/query fields.
                    $prepare = false;
                }

                $select[] = sprintf("'%s', %s", $key, (
                    $prepare ? $this->prepareField((string) $field) : $field
                ));
            }

            $select = join(', ', $select);
        }

        $select = trim((string) $select);
        $select || throw new QueryException('Empty select fields given');

        $select = $func . '(' . $select . ')';

        if ($as != '') {
            $select .= ' AS ' . $this->prepareField($as);
        }

        return $this->select($select, false);
    }

    /**
     * Add/append a "SELECT ..agg()" query into query stack.
     *
     * @alias aggregate()
     * @since 4.14
     */
    public function selectAgg(...$args): self
    {
        return $this->aggregate(...$args);
    }

    /**
     * Add/append a "SELECT count(..)" query into query stack.
     *
     * @alias aggregate() for count()
     * @since 4.14
     */
    public function selectCount(...$args): self
    {
        return $this->aggregate('count', ...$args);
    }

    /**
     * Add/append a "SELECT min(..)" query into query stack.
     *
     * @alias aggregate() for min()
     * @since 4.4
     */
    public function selectMin(...$args): self
    {
        return $this->aggregate('min', ...$args);
    }

    /**
     * Add/append "SELECT max(..)" query into query stack.
     *
     * @alias aggregate() for max()
     * @since 4.4
     */
    public function selectMax(...$args): self
    {
        return $this->aggregate('max', ...$args);
    }

    /**
     * Add/append a "SELECT avg(..)" query into query stack.
     *
     * @alias aggregate() avg()
     * @since 4.4
     */
    public function selectAvg(...$args): self
    {
        return $this->aggregate('avg', ...$args);
    }

    /**
     * Add/append a "SELECT sum(..)" query into query stack.
     *
     * @alias aggregate() sum()
     * @since 4.4
     */
    public function selectSum(...$args): self
    {
        return $this->aggregate('sum', ...$args);
    }

    /**
     * Add an "INSERT" query into query stack.
     *
     * @param  array|null            $data
     * @param  bool|null             $batch
     * @param  bool|null             $sequence
     * @param bool|string|array|null $return
     * @return self
     * @throws froq\database\QueryException
     */
    public function insert(array $data = null, bool $batch = null, bool $sequence = null, bool|string|array $return = null): self
    {
        $return && $this->return($return);

        // For with()/into() calls.
        if ($data === null) {
            return $this->add('insert', '1', false);
        }

        $fields = $values = [];

        if (!$batch) {
            // Eg: ["name" => "Kerem", ..].
            $fields = array_keys($data);
            $values = [array_values($data)];
        } elseif (isset($data['fields'], $data['values'])) {
            // Eg: ["fields" => ["name", ..], "values" => ["Kerem", ..]].
            $fields = (array) ($data['fields'] ?? []);
            $values = (array) ($data['values'] ?? []);
        } elseif (isset($data[0])) {
            // Eg: [["name" => "Kerem", ..], ..].
            $fields = array_keys($data[0]);
            $values = array_map(fn($d) => array_values($d), $data);
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

        $fields = $this->prepareFields($fields);

        return $this->add('insert', [$fields, $values, 'sequence' => $sequence], false);
    }

    /**
     * Add an "UPDATE" query into query stack.
     *
     * @param  array|null            $data
     * @param  bool                  $escape
     * @param bool|string|array|null $return
     * @return self
     * @throws froq\database\QueryException
     */
    public function update(array $data = null, bool $escape = true, bool|string|array $return = null): self
    {
        $return && $this->return($return);

        // For with() calls.
        if ($data === null) {
            return $this->add('update', '1', false);
        }

        $data || throw new QueryException('Empty data given for update');

        $sets = [];
        foreach ($data as $field => $value) {
            $sets[] = $this->db->escapeName($field)
                . ' = ' . ($escape ? $this->db->escape($value) : $value);
        }

        return $this->add('update', $sets, false);
    }

    /**
     * Add/append "DELETE" query into query stack.
     *
     * @param bool|string|array|null $return
     * @return self
     */
    public function delete(bool|string|array $return = null): self
    {
        $return && $this->return($return);

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
     * @param  string|array<string>|bool $fields
     * @param  string|array<string>|null $fetch
     * @return self
     * @since  4.18
     */
    public function return(string|array|bool $fields, string|array $fetch = null): self
    {
        // For PostgreSQL & Oracle only.
        if (!in_array($this->db->link()->driver(), ['pgsql', 'oci'], true)) {
            return $this;
        }

        $fields = ($fields === true) ? '*' : $this->prepareFields($fields);

        $fetch ??= $this->stack['return']['fetch'] ?? null;

        return $this->add('return', ['fields' => $fields, 'fetch' => $fetch], false);
    }

    /**
     * Add a "CONFLICT" clause into query stack.
     *
     * @param  string            $fields
     * @param  string            $action
     * @param  string|array|null $update
     * @param  array|null        $where
     * @return self
     * @throws froq\database\QueryException
     * @since  4.18
     */
    public function conflict(string $fields, string $action, string|array $update = null, array $where = null): self
    {
        $action = strtoupper($action);

        if (!in_array($action, ['NOTHING', 'UPDATE'], true)) {
            throw new QueryException('Invalid conflict action `%s` [valids: NOTHING, UPDATE]', $action);
        }

        // Comma separated update (fields).
        if (is_string($update) && $update != '*') {
            $update = split('\s*,\s*', $update);
        }

        if (!$update && $action == 'UPDATE') {
            throw new DatabaseException('Conflict action is `update`, but no update data given');
        }

        $fields = $this->prepareFields($fields);

        return $this->add('conflict', ['fields' => $fields, 'action' => $action,
                                       'update' => $update, 'where'  => $where], false);
    }

    /**
     * Set sequence directive of query stack.
     *
     * @param  string|array $option
     * @return self
     * @since  5.0
     */
    public function sequence(bool $option): self
    {
        $this->stack['insert']['sequence'] = $option;

        return $this;
    }

    /**
     * Set fetch directive of query stack.
     *
     * @param  string|array $option
     * @return self
     * @since  5.0
     */
    public function fetch(string|array $option): self
    {
        $this->stack['return']['fetch'] = $option;

        return $this;
    }

    /**
     * Add an increase command into query stack.
     *
     * @param  array     $field
     * @param  float|int $value
     * @param  bool      $return
     * @return self
     * @since  5.0
     */
    public function increase(string|array $field, int|float $value = 1, bool $return = false): self
    {
        $data = $this->prepareIncreaseDecrease('+', $field, $value, $return);

        return $this->update($data, false);
    }

    /**
     * Add a decrease command into query stack.
     *
     * @param  array     $field
     * @param  float|int $value
     * @param  bool      $return
     * @return self
     * @since  5.0
     */
    public function decrease(string|array $field, int|float $value = 1, bool $return = false): self
    {
        $data = $this->prepareIncreaseDecrease('-', $field, $value, $return);

        return $this->update($data, false);
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

        if ($on != '') {
            $on = 'ON (' . $this->prepare($on, $params) . ')';
        }

        return $this->add('join', [$type . 'JOIN ' . $this->prepareFields($to), $on]);
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
     * Add/append an "ON" clause into query stack for joins.
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
     * Add/append an "USING" clause into query stack for joins.
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
     * Add an "UNION" query into query stack, with/without "ALL" option.
     *
     * @param  string|Query $query
     * @param  array|null   $params
     * @param  bool         $prepare
     * @param  bool         $all
     * @return self
     * @since  5.0
     */
    public function union(string|Query $query, array $params = null, bool $prepare = true, bool $all = false): self
    {
        if ($prepare && is_string($query)) {
            $query = $this->prepare($query, $params);
        }

        return $this->add('union', [(string) $query, $all]);
    }

    /**
     * Add a "WITH" query into query stack, with/without "RECURSIVE" & "MATERIALIZED" options.
     *
     * @param  string       $name
     * @param  string|Query $query
     * @param  array|null   $params
     * @param  bool         $prepare
     * @param  string|null  $fields
     * @param  bool|null    $recursive
     * @param  bool|null    $materialized
     * @return self
     * @since  5.0
     */
    public function with(string $name, string|Query $query, array $params = null, bool $prepare = true,
        string $fields = null, bool $recursive = null, bool $materialized = null): self
    {
        $name = $this->prepareField($name);

        // Can be skipped in some situations.
        $fields && $fields = $this->prepareFields($fields);

        if ($prepare && is_string($query)) {
            $query = $this->prepare($query, $params);
        }

        return $this->add('with', [$name, (string) $query, $fields, $recursive, $materialized]);
    }

    /**
     * Add/append a "WHERE" query into query stack.
     *
     * @param  string|array     $where
     * @param  array|Query|null $params
     * @param  string|null      $op
     * @return self
     */
    public function where(string|array $where, array|Query $params = null, string $op = null): self
    {
        $op = $this->prepareOp($op ?: 'AND'); // @default=AND

        if (is_string($where)) {
            // Eg: (id = ?, 1).
            $this->add('where', [$this->prepare($where, $params), $op]);
        } else {
            static $signs = ['!', '<', '>'];

            // Eg: ([id => 1, active! => false, ..]).
            foreach ($where as $field => $param) {
                $sign = ' = ';
                if (in_array($field[-1], $signs, true)) {
                    $sign  = format(' %s ', ($field[-1] == '!') ? '!=' : $field[-1]);
                    $field = substr($field, 0, -1);
                }

                if (is_array($param)) {
                    $param = !str_contains($sign, '!')
                        ? new Sql('IN (' . join(', ', $this->escape($param)) . ')')
                        : new Sql('NOT IN (' . join(', ', $this->escape($param)) . ')');
                    $sign  = ' ';
                }

                $field = $this->prepareField($field);

                $this->add('where', [$this->prepare($field . $sign . '?', [$param]), $op]);
            }
        }

        return $this;
    }

    /**
     * Add/append a "WHERE" query into query stack for an equality condition.
     *
     * @param  string      $field
     * @param  mixed       $param
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereEqual(string $field, mixed $param, string $op = null): self
    {
        if (is_array($param)) {
            return $this->whereIn($field, $param);
        }
        if ($param instanceof Query) {
            return $this->where($this->prepareField($field) . ' = (?r)', $param, $op);
        }

        $param = (array) $param;
        $param || throw new QueryException('No parameter given');

        return $this->where($this->prepareField($field) . ' = ?', $param, $op);
    }

    /**
     * Add/append a "WHERE" query into query stack foor a non-equality condition.
     *
     * @param  string      $field
     * @param  mixed       $param
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotEqual(string $field, mixed $param, string $op = null): self
    {
        if (is_array($param)) {
            return $this->whereNotIn($field, $param);
        }
        if ($param instanceof Query) {
            return $this->where($this->prepareField($field) . ' != (?r)', $param, $op);
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
     * @param  array|Query $params
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereIn(string $field, array|Query $params, string $op = null): self
    {
        if ($params instanceof Query) {
            return $this->where($this->prepareField($field)
                . ' IN (' . $params->toString() . ')', null, $op);
        }

        $params || throw new QueryException('No parameters given');

        return $this->where($this->prepareField($field)
            . ' IN (' . $this->prepareWhereInPlaceholders($params) . ')', $params, $op);
    }

    /**
     * Add/append a "WHERE .. NOT IN (..)" query into query stack.
     *
     * @param  string      $field
     * @param  array|Query $params
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotIn(string $field, array|Query $params, string $op = null): self
    {
        if ($params instanceof Query) {
            return $this->where($this->prepareField($field)
                . ' NOT IN (' . $params->toString() . ')', null, $op);
        }

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
            $where = ($this->db->link()->driver() == 'pgsql')
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
            $where = ($this->db->link()->driver() == 'pgsql')
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
        if (is_string($query)) {
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
        if (is_string($query)) {
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
        return ($this->db->link()->driver() == 'pgsql')
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
        if (is_string($query)) {
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

        if ($rollup) {
            $field .= ($this->db->link()->driver() == 'mysql') ? ' WITH ROLLUP' : ' ROLLUP (' . (
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
            $field .= ' ' . $this->prepareOp($op, true);
        }

        // Extract options (with defaults).
        [$collate, $nulls] = [
            $options['collate'] ?? null,
            $options['nulls']   ?? null,
        ];

        // Eg: "tr_TR" or "tr_TR.utf8".
        if ($collate != null) {
            $collate = trim($collate);
            if ($this->db->link()->driver() == 'pgsql') {
                $collate = '"' . trim($collate, '"') . '"';
            }
            $collate = ' COLLATE ' . $collate;
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
            foreach (split('\s*,\s*', $field) as $i => $field) {
                [$field, $op] = split('\s+', trim($field), 2);
                $fields[$i] = $this->prepareField($field) . $collate;
                if ($op != null) {
                    $fields[$i] .= ' ' . $this->prepareOp($op, true);
                }
            }

            return $this->add('order', join(', ', $fields) . $nulls);
        }

        return $this->add('order', $this->prepareFields($field) . $collate . $nulls);
    }

    /**
     * Add/append an "ORDER BY random()/rand()" clause into query stack.
     *
     * @return self
     */
    public function orderByRandom(): self
    {
        return ($this->db->link()->driver() == 'pgsql')
            ? $this->add('order', 'random()') : $this->add('order', 'rand()');
    }

    /**
     * Index row set by given field.
     *
     * @param  string $field
     * @return self
     * @since  5.0
     */
    public function indexBy(string $field): self
    {
        return $this->add('index', $field, false);
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
     * @since  4.16, 5.0
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
        // From stack if given with return(), insert() etc.
        $fetch    ??= $this->stack['return']['fetch']    ?? null;
        $sequence ??= $this->stack['insert']['sequence'] ?? null;
        $index      = $this->stack['index']              ?? null;

        return $this->db->query($this->toString(), options: [
            'fetch' => $fetch, 'sequence' => $sequence, 'index' => $index
        ]);
    }

    /**
     * Execute a query stringifying current query stack.

     * @return int
     * @since  4.3
     */
    public function runExec(): int
    {
        return $this->db->execute($this->toString());
    }

    /**
     * Commit a "execute" query, reset stack and return self for next command.
     *
     * Note: This method is only useful when chaining is desired for executing
     * "delete" queries and passing to next query, eg. "insert", "update" etc.
     *
     * @return self
     * @since  5.0
     */
    public function commit(): self
    {
        $this->runExec();

        // Keep target table for next query choosing any of.
        $table = array_choose($this->stack, ['table', 'from', 'into'], '');

        return $this->reset()->table($table);
    }

    /**
     * Get a result row & running current query stack.
     *
     * @param  string|array<string>|null $fetch
     * @return array|object|null
     */
    public function get(string|array $fetch = null): array|object|null
    {
        // Optimize one-record queries, preventing sytax errors for non-select queries (PgSQL).
        if (!$this->has('limit')) {
            $ok = $this->has('select') || $this->db->link()->driver() != 'pgsql';
            $ok && $this->limit(1);
        }

        return $this->run($fetch)->rows(0);
    }

    /**
     * Get all result rows & running current query stack.
     *
     * Note: For pagination purposes, `paginate()` method must be called before this method.
     *
     * @param  string|array<string>|null $fetch
     * @param  int|null                  $limit
     * @return array|null
     */
    public function getAll(string|array $fetch = null, int $limit = null): array|null
    {
        $limit && $this->limit($limit);

        return $this->run($fetch)->rows();
    }

    /**
     * Get a result row as array & running current query stack.
     *
     * @return array|null
     * @since  4.7
     */
    public function getArray(): array|null
    {
        return $this->get('array');
    }

    /**
     * Get a result row as object & running current query stack.
     *
     * @return object|null
     * @since  4.7
     */
    public function getObject(): object|null
    {
        return $this->get('object');
    }

    /**
     * Get a result row as class instance & running current query stack.
     *
     * @return object|null
     * @since  5.0
     */
    public function getClass(string $class): object|null
    {
        return $this->get(['class', $class]);
    }

    /**
     * Get all result rows as array & running current query stack.
     *
     * @param  int|null $limit
     * @return array|null
     * @since  4.7
     */
    public function getArrayAll(int $limit = null): array|null
    {
        return $this->getAll('array', $limit);
    }

    /**
     * Get all result rows as object & running current query stack.
     *
     * @param  int|null $limit
     * @return array|null
     * @since  4.7
     */
    public function getObjectAll(int $limit = null): array|null
    {
        return $this->getAll('object', $limit);
    }

    /**
     * Get all result rows as class instance & running current query stack.
     *
     * @param  string   $class
     * @param  int|null $limit
     * @return array|null
     * @since  5.0
     */
    public function getClassAll(string $class, int $limit = null): array|null
    {
        return $this->getAll(['class', $class], $limit);
    }

    /**
     * Get all result rows as collection.
     *
     * @param  string|array<string>|null $fetch
     * @param  int|null                  $limit
     * @return froq\collection\Collection
     * @since  5.0
     */
    public function getCollection(string|array $fetch = null, int $limit = null): Collection
    {
        return new Collection($this->getAll($fetch, $limit));
    }

    /**
     * Run an insert query and get last insert id.
     *
     * @return int|null
     * @since  5.0
     */
    public function getId(): int|null
    {
        return $this->run()->id();
    }

    /**
     * Run an insert query and get all insert ids.
     *
     * @return array|null
     * @since  5.0
     */
    public function getIds(): array|null
    {
        return $this->run()->ids();
    }

    /**
     * Alias for getArray()/getArrayAll().
     *
     * @param  bool $all
     * @param  ...  $args
     * @return array|null
     * @since  5.0
     */
    public function array(bool $all = false, ...$args): array|null
    {
        return !$all ? $this->getArray() : $this->getArrayAll(...$args);
    }

    /**
     * Alias for getObject()/getObjectAll().
     *
     * @param  bool $all
     * @param  ...  $args
     * @return object|array|null
     * @since  5.0
     */
    public function object(bool $all = false, ...$args): object|array|null
    {
        return !$all ? $this->getObject() : $this->getObjectAll(...$args);
    }

    /**
     * @alias getCollection()
     * @since 5.0
     */
    public function collection(...$args)
    {
        return $this->getCollection(...$args);
    }

    /**
     * Get count result & running current query stack.
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
     * @param  string       $func
     * @param  string|array $field
     * @param  string|null  $as
     * @param  array|null   $options
     * @return self
     * @throws froq\database\QueryException
     * @since  4.4
     */
    public function aggregate(string $func, string|array $field, string $as = null, array $options = null): self
    {
        // Extract options (with defaults).
        [$distinct, $prepare, $order] = [
            $options['distinct'] ?? false,
            $options['prepare']  ?? true,
            $options['order']    ?? null,
        ];

        $distinct && $distinct = 'DISTINCT ';
        $prepare  && $field    = $this->prepareFields($field);

        // Dirty hijack..
        if ($order != null) {
            $order = current($this->clone(true)->orderBy($order)->stack['order']);
            $order = ' ORDER BY ' . $order;
        }

        if ($as != '') {
            $as = ' AS ' . $this->prepareField($as);
        }

        // Base functions.
        if (in_array($func, ['count', 'sum', 'min', 'max', 'avg'], true)) {
            return $this->select($func . '(' . $distinct . $field . $order . ')' . $as, false);
        }

        // PostgreSQL functions (no "_agg" suffix needed).
        if (in_array($func, ['array', 'string', 'json', 'json_object', 'jsonb', 'jsonb_object'], true)) {
            return $this->select($func . '_agg(' . $distinct . $field . $order . ')' . $as, false);
        }

        throw new QueryException('Invalid aggregate function `%s` [valids: count, sum, min, max, avg,'
            . ' array, string, json, json_object, jsonb, jsonb_object]', [$func]);
    }

    /**
     * Paginate query result setting offset/limit stuff & assigning ref'ed pager instance.
     *
     * @param  int|null               $page
     * @param  int|null               $limit
     * @param  froq\pager\Pager|null &$pager
     * @param  int|null               $count
     * @return self
     */
    public function paginate(int $page = null, int $limit = null, Pager &$pager = null, int $count = null): self
    {
        // Limit/offset. @default
        static $defaults = [10, 0];

        $page ??= Pager::getStartParam() ?? 1;
        $limit ??= $this->stack['limit'] ?? $defaults[0];
        // $offset = $this->stack['offset'] ?? $defaults[1]; // @discard

        $page = abs($page);
        $limit = abs($limit);

        $page = ($page > 0) ? $page : 1;
        $offset = ($page * $limit) - $limit;

        // This will also get a count() result if no count given.
        $pager ??= $this->db->initPager($count ?? $this->count(), ['start' => $page, 'stop' => $limit]);

        return $this->limit($limit, $offset);
    }

    /**
     * Paginate query result with given pager instance.
     *
     * @param  froq\pager\Pager $pager
     * @return self
     */
    public function paginateWith(Pager $pager): self
    {
        return $this->limit($pager->limit, $pager->offset);
    }

    /**
     * Init a `Sql` instance for a raw query/clause/statement.
     *
     * @param  string     $input
     * @param  array|null $params
     * @return froq\database\sql\Sql
     */
    public function sql(string $input, array $params = null): Sql
    {
        return new Sql($this->prepare($input, $params));
    }

    /**
     * Init a `Name` instance for an identifier (table, field etc).
     *
     * @param  string $input
     * @return froq\database\sql\Name
     */
    public function name(string $input): Name
    {
        return new Name($input);
    }

    /**
     * Append a raw or prepared query to current query stack, when needed with a with() query etc.
     *
     * @param  string|Query|null $query
     * @param  int|null          $indent
     * @return self
     * @since  5.0
     */
    public function append(string|Query $query = null, int $indent = null): self
    {
        $query ??= $this;

        if ($query instanceof Query) { // Get & clean behind.
            [$query] = [$this->toString($indent, false), $query->reset()];
        }

        return $this->add('append', $query);
    }

    /**
     * Clone query.
     *
     * @param  bool $reset
     * @return self
     */
    public function clone(bool $reset = false): self
    {
        $that = new self($this->db);

        if (!$reset) {
            $that->key   = $this->key;
            $that->stack = $this->stack;
        }

        return $that;
    }

    /**
     * Reset current key & stack.
     *
     * @return self
     */
    public function reset(): self
    {
        $this->key   = '';
        $this->stack = [];

        return $this;
    }

    /**
     * Check whether a clause/statement in query stack.
     *
     * @param  string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->stack[$key]);
    }

    /**
     * Pull an item from query stack.
     *
     * @param  string      $key
     * @param  string|null $subkey
     * @return mixed|null
     * @since  5.0
     */
    public function pull(string $key, string $subkey = null): mixed
    {
        if (isset($this->stack[$key])) {
            $value = $this->stack[$key];
            if (isset($subkey, $value[$subkey])) {
                $value = $value[$subkey];
            }
            unset($this->stack[$key]);
        }
        return $value ?? null;
    }

    /**
     * Drop an item from query stack.
     *
     * @param  string $key
     * @return self
     * @since  5.0
     */
    public function drop(string $key): self
    {
        unset($this->stack[$key]);

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
     * @param  int|bool|null $indent
     * @param  bool          $_trim @internal
     * @return string
     * @throws froq\database\QueryException
     */
    public function toString(int|bool $indent = null, bool $_trim = true): string
    {
        $n = $t = ' ';
        if ($indent) {
            $n = "\n"; $t = "\t";
        }

        $ret = '';

        if ($this->has('with')) {
            $ret = $this->toQueryString('with', $indent);
        }

        if ($this->has('append')) {
            $ret .= $n . join($n . $t, $this->stack['append']);
        }

        foreach (['insert', 'update', 'delete', 'select'] as $key) {
            $this->has($key) && $ret .= $this->toQueryString($key, $indent);
        }

        if ($ret == '') {
            throw new QueryException('No query ready to build, use select(), insert(), update(), delete(),'
                . ' aggregate() etc. first');
        }

        $_trim && $ret = trim($ret);

        return $ret;
    }

    /**
     * Get a part of query stack as string.
     *
     * @param  string        $key
     * @param  int|bool|null $indent
     * @return string
     * @throws froq\database\QueryException
     */
    public function toQueryString(string $key, int|bool $indent = null): string
    {
        $n  = ' '; $t  = '';
        $nt = ' '; $ts = '';
        if ($indent) {
            if ($indent == 1) {
                $n  = "\n"; $t  = "\t";
                $nt = "\n"; $ts = "\t";
            } elseif ($indent > 1) {
                $n  = "\n";      $t  = str_repeat("\t", $indent - 1);
                $nt = "\n" . $t; $ts = str_repeat("\t", $indent - 1 + 1); // Sub.
            }
        }

        $ret = '';

        $stack = $this->stack;
        switch ($key) {
            case 'with':
                if (isset($stack['with'])) {
                    $with = [];

                    foreach ($stack['with'] as [$name, $query, $fields, $recursive, $materialized]) {
                        $as = $recursive ? 'RECURSIVE ' . $name : $name;
                        if ($fields != null) {
                            $as .= ' (' . $fields . ')';
                        }

                        $as .= ' AS ';
                        if ($materialized !== null) {
                            $as .= ($materialized ? 'MATERIALIZED' : 'NOT MATERIALIZED') . ' ';
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

                    if ($stack['select'] != '*') {
                        foreach ($stack['select'] as $field) {
                            $select[] = $n . $ts . $field;
                        }
                        $select = join(',', $select);
                    }

                    $ret = $nt . 'SELECT ' . trim($select)
                         . $nt . 'FROM '   . $table;

                    isset($stack['join'])   && $ret .= $nt . $this->toQueryString('join', $indent);
                    isset($stack['where'])  && $ret .= $nt . $this->toQueryString('where', $indent);

                    if (isset($stack['union'])) {
                        foreach ($stack['union'] as [$query, $all]) {
                            $ret .= $nt . 'UNION ' . ($all ? 'ALL ' : '');
                            if ($indent >= 1) {
                                $ret .= '(' . $n . $ts . $query . $nt . ')';
                            } else {
                                $ret .= '(' . $query . ')';
                            }
                        }
                    }

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

                        $ret .= match ($driver = $this->db->link()->driver()) {
                            'pgsql' => $nt . 'ON CONFLICT (' . $fields . ') DO ' . $action,
                            'mysql' => $nt . 'ON DUPLICATE KEY ' . ($action = 'UPDATE'),
                            default => throw new QueryException('Method conflict() available for PgSQL & MySQL only')
                        };

                        if ($action == 'UPDATE') {
                            // Use all insert fields for excluded stuff below.
                            if ($update == '*') {
                                $update = [$stack['insert'][0]];
                            }

                            $that = $this->clone(true)->table('@');

                            // Handle PostgreSQL's stuff (eg: update => ['name', ..]).
                            if (is_list($update)) {
                                $temp = $this->prepareFields($update);
                                $sets = [sprintf('(%s) = (%s)', $temp, implode(', ', array_map(
                                    fn($t) => 'EXCLUDED.' . $t, explode(', ', $temp)))
                                )];
                            } else {
                                $temp = [];
                                foreach ($update as $field => $value) {
                                    $field = $this->prepareField($field);
                                    // Handle PostgreSQL's stuff (eg: update => ['name' => 'excluded.name', ..]).
                                    if (is_string($value) && str_has_prefix($value, 'EXCLUDED.', true)) {
                                        $temp[$field] = 'EXCLUDED.' . $this->prepareField(substr($value, 9));
                                    } else {
                                        $temp[$field] = $this->escape($value);
                                    }
                                }

                                $sets = $that->update($temp, false)->pull('update');
                            }

                            $ret .= ($driver == 'pgsql')
                                  ? $nt . 'SET ' . join(', ', $sets)
                                  : $nt . join(', ', $sets);

                            if ($where != null) {
                                $where = (array) $where;
                                [$where, $params] = array_select($where, [0, 1]);
                                $ret .= $nt . trim($that->where((string) $where, (array) $params)
                                    ->toQueryString('where'));
                            }

                            unset($that);
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
                         . $nt . 'SET '    . join(',' . $nt . $t, $stack['update']);

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
                            $nx   = $wheres[$i + 1] ?? null;
                            $nxnx = $wheres[$i + 2] ?? null;
                            $nxop = $nx[1] ?? '';

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
                    $joins = [];

                    foreach ($stack['join'] as $join) {
                        @ [$content, $context] = $join;
                        if (!$context) {
                            throw new QueryException('No join context yet, use 2. argument of join() or call'
                                . ' on()/using() method');
                        }
                        $joins[] = trim($content . ' ' . $context);
                    }

                    $ret .= join($n, $joins);
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
     * Escape an input.
     *
     * @param  mixed       $input
     * @param  string|null $format
     * @return mixed
     */
    public function escape(mixed $input, string $format = null): mixed
    {
        return $this->db->escape($input, $format);
    }

    /**
     * Escape a name input.
     *
     * @param  string $input
     * @return string
     */
    public function escapeName(string $input): string
    {
        return $this->db->escapeName($input);
    }

    /**
     * Prepare an input.
     *
     * @param  string           $input
     * @param  array|Query|null $params
     * @return string
     * @throws froq\database\QueryException
     */
    public function prepare(string $input, array|Query $params = null): string
    {
        if ($params && $params instanceof Query) {
            $params = [$params->toString()];
        }

        $input = trim($input);
        if ($input == '') {
            throw new QueryException('Empty input given');
        }

        // Check names (eg: '@id ..', 1 or '@[id, ..]').
        if (str_contains($input, '@')) {
            return $this->db->prepare($input, $params);
        }

        return $params ? $this->db->prepare($input, $params) : $input;
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
        if ($field == '') {
            throw new QueryException('Empty field given');
        }

        // Check names (eg: '@id ..', 1 or '@[id, ..]').
        if (str_contains($field, '@')) {
            return $this->db->prepareName($field);
        }

        return $this->db->escapeName($field);
    }

    /**
     * Prepare fields.
     *
     * @param  string|array<string> $fields
     * @return string
     * @throws froq\database\QueryException
     */
    public function prepareFields(string|array $fields): string
    {
        if (is_array($fields)) {
            $fields = join(', ', $fields);
        }

        $fields = trim($fields);
        if ($fields == '') {
            throw new QueryException('Empty fields given');
        }

        // Check names (eg: '@id ..', 1 or '@[id, ..]').
        if (str_contains($fields, '@')) {
            return $this->db->prepareName($fields);
        }

        return strpbrk($fields, ', ') ? $this->db->escapeNames($fields)
                                      : $this->db->escapeName($fields);
    }

    /**
     * Prepare an operator.
     *
     * @param  string|int $op
     * @param  bool       $numeric
     * @return string
     * @throws froq\database\QueryException
     */
    public function prepareOp(string|int $op, bool $numeric = false): string
    {
        static $ops = ['AND', 'OR', 'ASC', 'DESC'];

        $op = strtoupper(trim((string) $op));
        if (in_array($op, $ops, true)) {
            return $op;
        }
        if ($numeric && in_array($op, ['1', '-1'], true)) {
            return ($op == '1') ? 'ASC' : 'DESC';
        }

        throw new QueryException('Invalid op `%s` [valids: AND, OR for where & ASC, DESC, 1, -1 for order]', $op);
    }

    /**
     * Prepare increase/decrease data for update.
     *
     * @param  string       $sign
     * @param  string|array $field
     * @param  int|float    $value
     * @param  bool         $return
     * @return array
     * @throws froq\database\QueryException
     * @since  5.0
     */
    private function prepareIncreaseDecrease(string $sign, string|array $field, int|float $value = 1, bool $return = false): array
    {
        $data = [];

        // Eg: (.., "x", 1, ..).
        if (is_string($field)) {
            $data[$field] = $this->db->escapeName($field) . ' ' . $sign . ' ' . $value;

            $return && $this->return($field);
        }
        // Eg: (.., ["x" => 1, "y" => 1], ..).
        else {
            // Cast values as float or leave it exception below.
            $field  = array_map(fn($v) => is_numeric($v) ? (float) $v : $v, $field);
            $fields = [];

            foreach ($field as $name => $value) {
                is_string($name)  || throw new QueryException('Invalid field name `%s`', $name);
                is_number($value) || throw new QueryException('Invalid field value `%s`', $value);

                $data[$name] = $this->db->escapeName($name) . ' ' . $sign . ' ' . $value;

                $return && $fields[] = $name;
            }

            $return && $this->return($fields);
        }

        return $data;
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
     * Add a clause/statement to query stack.
     *
     * @param  string $key
     * @param  mixed  $value
     * @param  bool   $merge
     * @return self
     */
    private function add(string $key, mixed $value, bool $merge = true): self
    {
        $merge && $value = [...($this->stack[$key] ?? []), $value];

        $this->key         = $key; // Tick for last call.
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
            throw new QueryException(
                'No `%s` statement yet in query stack to apply `%s` operator, '.
                'call %s() first to apply', [$key, $op, $key]
            );
        }

        $this->stack[$key][count($this->stack[$key]) - 1][1] = $value;

        return $this;
    }
}
