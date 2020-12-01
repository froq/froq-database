<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database;

use froq\pager\Pager;
use froq\database\{QueryTrait, QueryException, Database, Result};
use froq\database\sql\{Sql, Name};

/**
 * Query.
 *
 * Represents a query builder entity which mostly fulfills all building needs with descriptive
 * methods.
 *
 * @package froq\database
 * @object  froq\database\Query
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 */
final class Query
{
    /**
     * Query trait.
     * @see froq\database\QueryTrait
     */
    use QueryTrait;

    /**
     * Db.
     * @var froq\database\Database
     */
    private Database $db;

    /**
     * Stack, for statements.
     * @var array
     */
    private array $stack = [];

    /**
     * Key, tick for last call via add().
     * @var string
     * @since 5.0
     */
    private string $key;

    /**
     * Constructor.
     * @param froq\database\Database $db
     * @param string|null            $table
     */
    public function __construct(Database $db, string $table = null)
    {
        $this->db = $db;

        $table && $this->table($table);
    }

    /**
     * String magic.
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Db.
     * @return froq\database\Database
     */
    public function db(): Database
    {
        return $this->db;
    }

    /**
     * Table.
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
     * From.
     * @param  string|Query $from
     * @param  string|null  $as
     * @param  bool         $prepare
     * @return self
     * @throws froq\database\QueryException
     */
    public function from($from, string $as = null, bool $prepare = true): self
    {
        if (is_string($from)) {
            if ($as != '') {
                $from .= ' AS ' . $this->prepareField($as);
            }
            return $this->table($from, $prepare);
        }

        if ($from instanceof Query) {
            $from = '(' . $from->toString() . ')';
            if ($as != '') {
                $from .= ' AS ' . $this->prepareField($as);
            }
            return $this->table($from, false);
        }

        throw new QueryException("Invalid from type '%s', valids are: string, %s", [gettype($query), Query::class]);
    }

    /**
     * Select.
     * @param  string $select
     * @param  bool   $prepare
     * @return self
     * @throws froq\database\QueryException
     */
    public function select(string $select, bool $prepare = true): self
    {
        $select = trim($select);
        if ($select == '') {
            throw new QueryException('Empty select given');
        }

        if ($prepare) {
            $select = $this->prepareFields($select);
        }

        return $this->add('select', $select);
    }

    /**
     * Select raw.
     * @param  string      $select
     * @param  string|null $as
     * @return self
     * @throws froq\database\QueryException
     */
    public function selectRaw(string $select, string $as = null): self
    {
        $select = trim($select);
        if ($select == '') {
            throw new QueryException('Empty select given');
        }

        $select = '(' . $select . ')';
        if ($as != '') {
            $select .= ' AS ' . $this->prepareField($as);
        }

        return $this->add('select', $select);
    }

    /**
     * Select query.
     * @param  string|Query $query
     * @param  string       $as
     * @return self
     * @throws froq\database\QueryException
     */
    public function selectQuery($query, string $as): self
    {
        if (is_string($query)) {
            return $this->selectRaw($query, $as);
        }

        if ($query instanceof Query) {
            return $this->selectRaw($query->toString(), $as);
        }

        throw new QueryException("Invalid query type '%s', valids are: string, %s", [gettype($query), Query::class]);
    }

    /**
     * Select json.
     * @param  array  $fields
     * @param  string $as
     * @return self
     * @throws froq\database\QueryException
     */
    public function selectJson(array $fields, string $as): self
    {
        $select = null;
        $selectType = isset($fields[0]) ? 1 : 2; // Simple check set/map array.

        switch ($this->db->link()->pdoDriver()) {
            case 'pgsql':
                $func = ($selectType == 1) ? 'json_build_array' : 'json_build_object';
                break;
            case 'mysql':
                $func = ($selectType == 1) ? 'json_array' : 'json_object';
                break;
            default:
                throw new QueryException("Method '%s()' available for PgSQL & MySQL only", __method__);
        }

        if ($selectType == 1) {
            $select = $this->prepareFields(join(',', $fields));
        } else {
            foreach ($fields as $fieldKey => $fieldName) {
                $select[] = sprintf("'%s', %s", $fieldKey, $this->prepareField($fieldName));
            }
            $select = join(', ', $select);
        }

        $select = trim((string) $select);
        if ($select == '') {
            throw new QueryException('Empty select fields given');
        }

        return $this->select($func . '(' . $select . ') AS ' . $this->prepareField($as), false);
    }

    /**
     * Select agg.
     * @alias of aggregate()
     * @since 4.14
     */
    public function selectAgg(...$args): self
    {
        return $this->aggregate(...$args);
    }

    /**
     * Select count.
     * @alias of aggregate(), for count()
     * @since 4.14
     */
    public function selectCount(...$args): self
    {
        return $this->aggregate('count', ...$args);
    }

    /**
     * Select min.
     * @alias of aggregate(), for min()
     * @since 4.4
     */
    public function selectMin(...$args): self
    {
        return $this->aggregate('min', ...$args);
    }

    /**
     * Select max.
     * @alias of aggregate(), for max()
     * @since 4.4
     */
    public function selectMax(...$args): self
    {
        return $this->aggregate('max', ...$args);
    }

    /**
     * Select avg.
     * @alias of aggregate(), for avg()
     * @since 4.4
     */
    public function selectAvg(...$args): self
    {
        return $this->aggregate('avg', ...$args);
    }

    /**
     * Select sum.
     * @alias of aggregate(), for sum()
     * @since 4.4
     */
    public function selectSum(...$args): self
    {
        return $this->aggregate('sum', ...$args);
    }

    /**
     * Insert.
     * @param  array     $data
     * @param  bool|null $batch
     * @param  bool|null $sequence
     * @return self
     * @throws froq\database\QueryException
     */
    public function insert(array $data, bool $batch = null, bool $sequence = null): self
    {
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
            throw new QueryException('Both fields & values should not be empty for insert');
        }

        $fieldsCount = count($fields);
        foreach ($values as $i => $value) {
            $value = (array) $value;
            if (count($value) != $fieldsCount) {
                throw new QueryException("Count of value set '%s' not matched with fields count", $i);
            }
            $values[$i] = '(' . join(', ', $this->db->escape($value)) . ')';
        }

        $fields = $this->prepareFields(join(', ', $fields));

        return $this->add('insert', [$fields, $values, 'sequence' => $sequence], false);
    }

    /**
     * Update.
     * @param  array $data
     * @return self
     * @throws froq\database\QueryException
     */
    public function update(array $data): self
    {
        if (!$data) {
            throw new QueryException('Empty data given for update');
        }

        $set = [];
        foreach ($data as $name => $value) {
            $set[] = $this->db->escapeName($name) . ' = ' . $this->db->escape($value);
        }

        return $this->add('update', $set, false);
    }

    /**
     * Delete.
     * @return self
     */
    public function delete(): self
    {
        return $this->add('delete', '1', false);
    }

    /**
     * Return (for "RETURNING" clause).
     * @param  string                    $fields
     * @param  string|array<string>|null $fetch
     * @return self
     * @since  4.18
     */
    public function return(string $fields, $fetch = null): self
    {
        $fields = $this->prepareFields($fields);

        return $this->add('return', ['fields' => $fields, 'fetch' => $fetch], false);
    }

    /**
     * Conflict (for "CONFLICT" clause).
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

        if (!in_array($action, ['NOTHING', 'UPDATE'])) {
            throw new QueryException("Invalid action '%s' for conflict, valids are: NOTHING, UPDATE",
                [$action]);
        }

        $fields = $this->prepareFields($fields);

        return $this->add('conflict', ['fields' => $fields, 'action' => $action,
                                       'update' => $update, 'where' => $where], false);
    }

    /**
     * Join.
     * @param  string      $to
     * @param  string|null $on
     * @param  array|null  $onParams
     * @param  string|null $type
     * @return self
     */
    public function join(string $to, string $on = null, array $onParams = null, string $type = null): self
    {
        $to = $this->prepareFields($to);
        $type && $type = strtoupper($type) . ' ';
        $on && $on = 'ON (' . $this->prepare($on, $onParams) . ')';

        return $this->add('join', [$type . 'JOIN ' . $to, $on]);
    }

    /**
     * On.
     * @param  string|null $on
     * @param  array|null  $onParams
     * @return self
     * @since  5.0
     */
    public function on(string $on = null, array $onParams = null): self
    {
        return $this->addTo('join', 'ON (' . $this->prepare($on, $onParams) . ')');
    }

    /**
     * Using.
     * @param  string $fields
     * @return self
     * @since  5.0
     */
    public function using(string $fields): self
    {
        return $this->addTo('join', 'USING (' . $this->prepareFields($fields) . ')');
    }

    /**
     * Join left.
     * @param  string     $to
     * @param  string     $on
     * @param  array|null $onParams
     * @param  bool       $outer
     * @return self
     */
    public function joinLeft(string $to, string $on, array $onParams = null, bool $outer = false): self
    {
        return $this->join($to, $on, $onParams, 'LEFT' . ($outer ? ' OUTER' : ''));
    }

    /**
     * Join right.
     * @param  string     $to
     * @param  string     $on
     * @param  array|null $onParams
     * @param  bool       $outer
     * @return self
     */
    public function joinRight(string $to, string $on, array $onParams = null, bool $outer = false): self
    {
        return $this->join($to, $on, $onParams, 'RIGHT' . ($outer ? ' OUTER' : ''));
    }

    /**
     * Where.
     * @param  string      $where
     * @param  array|null  $whereParams
     * @param  string|null $op
     * @return self
     */
    public function where(string $where, array $whereParams = null, string $op = null): self
    {
        $whereParams && $where = $this->prepare($where, $whereParams);

        return $this->add('where', [$where, $this->prepareOp($op ?: 'AND')]);
    }

    /**
     * Where equal.
     * @param  string      $field
     * @param  any         $fieldParam
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereEqual(string $field, $fieldParam, string $op = null): self
    {
        $fieldParam = (array) $fieldParam;
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) . ' = ?', $fieldParam, $op);
    }

    /**
     * Where not equal.
     * @param  string      $field
     * @param  any         $fieldParam
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotEqual(string $field, $fieldParam, string $op = null): self
    {
        $fieldParam = (array) $fieldParam;
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) . ' != ?', $fieldParam, $op);
    }

    /**
     * Where null.
     * @param  string      $field
     * @param  string|null $op
     * @return self
     */
    public function whereNull(string $field, string $op = null): self
    {
        return $this->whereIs($field, null, $op);
    }

    /**
     * Where not null.
     * @param  string      $field
     * @param  string|null $op
     * @return self
     */
    public function whereNotNull(string $field, string $op = null): self
    {
        return $this->whereIsNot($field, null, $op);
    }

    /**
     * Where is.
     * @param  string      $field
     * @param  ?bool       $value
     * @param  string|null $op
     * @return self
     * @since  5.0
     */
    public function whereIs(string $field, ?bool $value, string $op = null): self
    {
        $value = is_null($value) ? 'NULL' : ($value ? 'TRUE' : 'FALSE');

        return $this->where($this->prepareField($field) . ' IS ' . $value, null, $op);
    }

    /**
     * Where is not.
     * @param  string      $field
     * @param  ?bool       $value
     * @param  string|null $op
     * @return self
     * @since  5.0
     */
    public function whereIsNot(string $field, ?bool $value, string $op = null): self
    {
        $value = is_null($value) ? 'NULL' : ($value ? 'TRUE' : 'FALSE');

        return $this->where($this->prepareField($field) . ' IS NOT ' . $value, null, $op);
    }

    /**
     * Where in.
     * @param  string      $field
     * @param  any         $fieldParams
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereIn(string $field, $fieldParams, string $op = null): self
    {
        $fieldParams = (array) $fieldParams;
        if (!$fieldParams) {
            throw new QueryException('No parameters given');
        }

        return $this->where($this->prepareField($field)
            . ' IN (' . $this->prepareWhereInPlaceholders($fieldParams) . ')', $fieldParams, $op);
    }

    /**
     * Where not in.
     * @param  string      $field
     * @param  any         $fieldParams
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotIn(string $field, $fieldParams, string $op = null): self
    {
        $fieldParams = (array) $fieldParams;
        if (!$fieldParams) {
            throw new QueryException('No parameters given');
        }

        return $this->where($this->prepareField($field)
            . ' NOT IN (' . $this->prepareWhereInPlaceholders($fieldParams) . ')', $fieldParams, $op);
    }

    /**
     * Where between.
     * @param  string      $field
     * @param  array       $fieldParams
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereBetween(string $field, array $fieldParams, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryException('No parameters given');
        }

        return $this->where($this->prepareField($field) . ' BETWEEN ? AND ?', $fieldParams, $op);
    }

    /**
     * Where not between.
     * @param  string      $field
     * @param  array       $fieldParams
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotBetween(string $field, array $fieldParams, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryException('No parameters given');
        }

        return $this->where($this->prepareField($field) . ' NOT BETWEEN ? AND ?', $fieldParams, $op);
    }

    /**
     * Where less than.
     * @param  string        $field
     * @param  string|number $fieldParam
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereLessThan(string $field, $fieldParam, string $op = null): self
    {
        $fieldParam = (array) $fieldParam;
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) . ' < ?', $fieldParam, $op);
    }

    /**
     * Where less than equal.
     * @param  string        $field
     * @param  string|number $fieldParam
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereLessThanEqual(string $field, $fieldParam, string $op = null): self
    {
        $fieldParam = (array) $fieldParam;
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) . ' <= ?', $fieldParam, $op);
    }

    /**
     * Where greater than.
     * @param  string        $field
     * @param  string|number $fieldParam
     * @param  string|null $op
     * @return self
     */
    public function whereGreaterThan(string $field, $fieldParam, string $op = null): self
    {
        $fieldParam = (array) $fieldParam;
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) . ' > ?', $fieldParam, $op);
    }

    /**
     * Where greater than equal.
     * @param  string        $field
     * @param  string|number $fieldParam
     * @param  string|null $op
     * @return self
     */
    public function whereGreaterThanEqual(string $field, $fieldParam, string $op = null): self
    {
        $fieldParam = (array) $fieldParam;
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) . ' >= ?', $fieldParam, $op);
    }

    /**
     * Where like.
     * @param  string       $field
     * @param  string|array $fieldParams
     * @param  bool         $ilike
     * @param  string|null  $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereLike(string $field, $fieldParams, bool $ilike = false, string $op = null): self
    {
        $fieldParams = (array) $fieldParams;
        if (!$fieldParams) {
            throw new QueryException('No parameters given');
        }

        [$field, $search] = [$this->prepareField($field), $this->prepareWhereLikeSearch($fieldParams)];

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
     * Where not like.
     * @param  string       $field
     * @param  string|array $fieldParams
     * @param  bool         $ilike
     * @param  string|null  $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotLike(string $field, $fieldParams, bool $ilike = false, string $op = null): self
    {
        $fieldParams = (array) $fieldParams;
        if (!$fieldParams) {
            throw new QueryException('No parameters given');
        }

        [$field, $search] = [$this->prepareField($field), $this->prepareWhereLikeSearch($fieldParams)];

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
     * Where exists.
     * @param  string      $query
     * @param  array|null  $queryParams
     * @param  string|null $op
     * @return self
     */
    public function whereExists(string $query, array $queryParams = null, string $op = null): self
    {
        $queryParams && $query = $this->prepare($query, $queryParams);

        return $this->where('EXISTS (' . $query . ')', null, $op);
    }

    /**
     * Where not exists.
     * @param  string     $query
     * @param  array|null $queryParams
     * @param  string     $op
     * @return self
     */
    public function whereNotExists(string $query, array $queryParams = null, string $op = null): self
    {
        $queryParams && $query = $this->prepare($query, $queryParams);

        return $this->where('NOT EXISTS (' . $query . ')', null, $op);
    }

    /**
     * Where random.
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
     * Having.
     * @param  string     $query
     * @param  array|null $queryParams
     * @return self
     */
    public function having(string $query, array $queryParams = null): self
    {
        if ($queryParams) {
            $query = $this->prepare($query, $queryParams);
        }

        return $this->add('having', $query, false);
    }

    /**
     * Group by.
     * @param  string      $field
     * @param  string|bool $rollup
     * @return self
     */
    public function groupBy(string $field, $rollup = null): self
    {
        $field = $this->prepareFields($field);

        if ($rollup) {
            $field .= ($this->db->link()->pdoDriver() == 'mysql')
                    ? ' WITH ROLLUP'
                    : ' ROLLUP (' . (
                        is_string($rollup) ? $this->prepareFields($rollup) : $field
                    ) . ')';
        }

        return $this->add('group', $field);
    }

    /**
     * Order by.
     * @param  string|Sql      $field
     * @param  string|int|null $op
     * @param  array|null      $options
     * @return self
     * @throws froq\database\QueryException
     */
    public function orderBy($field, $op = null, array $options = null): self
    {
        if (!is_string($field) && !$check = ($field instanceof Sql)) {
            throw new QueryException("Invalid field type '%s', valids are: string, %s",
                [gettype($field), Sql::class]);
        }

        $field = trim((string) $field);
        if ($field == '') {
            throw new QueryException('No field given');
        }

        // Eg: ("id", "ASC") or ("id", 1) or ("id", -1).
        if ($op != null && (is_string($op) || is_int($op))) {
            $field .= ' ' . $this->prepareOp(strval($op), true);
        }

        // Extract options (with defaults).
        [$collate, $nulls] = [
            $options['collate'] ?? null,
            $options['nulls'] ?? null,
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
        if (!empty($check)) {
            return $this->add('order', $field . $collate . $nulls);
        }

        // Eg: ("id ASC") or ("id ASC, name DESC").
        if (strpos($field, ' ')) {
            $fields = [];
            foreach (explode(',', $field) as $i => $field) {
                @ [$field, $op] = explode(' ', trim($field));
                $fields[$i] = $this->prepareField($field) . $collate;
                if ($op) {
                    $fields[$i] .= ' ' . $this->prepareOp($op, true);
                }
            }

            return $this->add('order', implode(', ', $fields) . $nulls);
        }

        return $this->add('order', $this->prepareField($field) . $collate . $nulls);
    }

    /**
     * Order by random.
     * @return self
     */
    public function orderByRandom(): self
    {
        return ($this->db->link()->pdoDriver() == 'pgsql')
            ? $this->add('order', 'random()') : $this->add('order', 'rand()');
    }

    /**
     * Limit.
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
     * Offset.
     * @param  int $offset
     * @return self
     * @throws froq\database\QueryException
     */
    public function offset(int $offset): self
    {
        if (!$this->has('limit')) {
            throw new QueryException('Limit not set yet, call limit() first for offset');
        }

        return $this->add('offset', abs($offset), false);
    }

    /**
     * As.
     * @param  string $as
     * @param  bool   $prepare
     * @return self
     * @throws froq\database\QueryException
     * @since  4.16, 5.0 Optimized for table statement.
     */
    public function as(string $as, bool $prepare = true): self
    {
        if (empty($this->key)) {
            throw new QueryException('No table/select statement yet in query stack '
                . 'to apply AS operator, call one of them first to apply');
        }

        $prepare && $as = $this->prepareField($as);

        switch ($this->key) {
            case 'table':
                if (!strpos($this->stack['table'], ' AS ')) {
                    $this->stack['table'] .= ' AS ' . $as; // Concat.
                }
                break;
            case 'select':
                if (!strpos($this->stack['select'][count($this->stack['select']) - 1], ' AS ')) {
                    $this->stack['select'][count($this->stack['select']) - 1] .= ' AS ' . $as;
                }
                break;
            default:
                throw new QueryException("Invalid key '%s' for as()", $this->key);
        }

        return $this;
    }

    /**
     * Or.
     * @return self
     * @throws froq\database\QueryException
     */
    public function or(): self
    {
        return $this->addTo('where', 'OR');
    }

    /**
     * And.
     * @return self
     * @throws froq\database\QueryException
     */
    public function and(): self
    {
        return $this->addTo('where', 'AND');
    }

    /**
     * Asc.
     * @param  string     $field
     * @param  array|null $options
     * @return self
     */
    public function asc(string $field = 'id', string $options = null): self
    {
        return $this->orderBy($field, 'ASC', $options);
    }

    /**
     * Desc.
     * @param  string     $field
     * @param  array|null $options
     * @return self
     */
    public function desc(string $field = 'id', string $options = null): self
    {
        return $this->orderBy($field, 'DESC', $options);
    }

    /**
     * Id.
     * @param  int|string $id
     * @return self
     */
    public function id($id): self
    {
        return $this->whereEqual('id', $id);
    }

    /**
     * Run.
     * @param  string|array<string>|null $fetch
     * @param  bool|null                 $sequence
     * @return froq\database\Result
     */
    public function run($fetch = null, bool $sequence = null): Result
    {
        // Get from stack if given with return() / insert().
        $fetch ??= $this->stack['return']['fetch'] ?? null;
        $sequence ??= $this->stack['insert']['sequence'] ?? null;

        return $this->db->query($this->toString(), null, ['fetch' => $fetch, 'sequence' => $sequence]);
    }

    /**
     * Run exec.
     * @return ?int
     * @since  4.3
     */
    public function runExec(): ?int
    {
        return $this->db->execute($this->toString());
    }

    /**
     * Get.
     * @param  string|array<string>|null $fetch
     * @return ?array|?object
     */
    public function get($fetch = null)
    {
        // Optimize one-record query.
        $this->has('limit') || $this->limit(1);

        return $this->db->get($this->toString(), null, $fetch);
    }

    /**
     * Get all.
     * @param  string|array<string>|null  $fetch
     * @param  froq\pager\Pager|null     &$pager
     * @param  int|null                   $limit
     * @return ?array
     */
    public function getAll($fetch = null, Pager &$pager = null, int $limit = null): ?array
    {
        if ($limit === null) {
            return $this->db->getAll($this->toString(), null, $fetch);
        }

        $this->paginate($pager, $limit);

        return $this->db->getAll($this->toString(), null, $fetch);
    }

    /**
     * Get array.
     * @return ?array
     * @since  4.7
     */
    public function getArray(): ?array
    {
        return $this->get('array');
    }

    /**
     * Get object.
     * @return ?object
     * @since  4.7
     */
    public function getObject(): ?object
    {
        return $this->get('object');
    }

    /**
     * Get array all.
     * @param  froq\pager\Pager|null &$pager
     * @param  int|null               $limit
     * @return ?array
     * @since  4.7
     */
    public function getArrayAll(Pager &$pager = null, int $limit = null): ?array
    {
        return $this->getAll('array', $pager, $limit);
    }

    /**
     * Get object all.
     * @param  froq\pager\Pager|null &$pager
     * @param  int|null               $limit
     * @return ?array
     * @since  4.7
     */
    public function getObjectAll(Pager &$pager = null, int $limit = null): ?array
    {
        return $this->getAll('object', $pager, $limit);
    }

    /**
     * Count.
     * @return int
     */
    public function count(): int
    {
        // Prevent empty query exception.
        $this->has('select') || $this->add('select', '1');

        return $this->db->countQuery($this->toString());
    }

    /**
     * Aggregate.
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
            $options['prepare'] ?? true,
            $options['order'] ?? null
        ];

        $distinct = $distinct ? 'DISTINCT ' : '';
        $field = $prepare ? $this->prepareField($field) : $field;

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

        throw new QueryException("Invalid aggregate function '%s', valids are: count, sum, min, max, avg, "
            . "array, string, json, json_object, jsonb, jsonb_object", [$func]);
    }

    /**
     * Paginate.
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
     * Paginate.
     * @param  froq\pager\Pager $pager
     * @return self
     */
    public function paginateWith(Pager $pager): self
    {
        return $this->limit($pager->getLimit(), $pager->getOffset());
    }

    /**
     * Sql.
     * @param  string     $in
     * @param  array|null $params
     * @return froq\database\sql\Sql
     */
    public function sql(string $in, array $params = null): Sql
    {
        $params && $in = $this->prepare($in, $params);

        return new Sql($in);
    }

    /**
     * Name.
     * @param  string $in
     * @return froq\database\sql\Name
     */
    public function name(string $in): Name
    {
        return new Name($in);
    }

    /**
     * Reset.
     * @return self
     */
    public function reset(): self
    {
        $this->stack = [];

        return $this;
    }

    /**
     * To array.
     * @return array
     */
    public function toArray(): array
    {
        return $this->stack;
    }

    /**
     * To string.
     * @param  int|null $indent
     * @return string
     * @throws froq\database\QueryException
     */
    public function toString(int $indent = null): string
    {
        $ret = '';

        if ($this->has('select')) {
            $ret = $this->toQueryString('select', $indent);
        } elseif ($this->has('insert')) {
            $ret = $this->toQueryString('insert', $indent);
        } elseif ($this->has('update')) {
            $ret = $this->toQueryString('update', $indent);
        } elseif ($this->has('delete')) {
            $ret = $this->toQueryString('delete', $indent);
        } else {
            throw new QueryException('No query ready to build, use select(), insert(), update(), delete(), '
                . 'aggregate() etc. first');
        }

        return $ret;
    }

    /**
     * To query string.
     * @param  string   $key
     * @param  int|null $indent
     * @return string
     * @throws froq\database\QueryException
     */
    public function toQueryString(string $key, int $indent = null): string
    {
        $n = $t = ' ';
        $nt = ' '; $ts = '';
        if ($indent) {
            if ($indent == 1) {
                $n = "\n"; $t = "\t";
                $nt = "\n"; $ts = "\t";
            } elseif ($indent > 1) {
                $n = "\n"; $t = str_repeat("\t", $indent - 1);
                $nt = "\n" . $t; $ts = str_repeat("\t", $indent - 1 + 1); // Sub.
            }
        }

        $stack = $this->stack;
        $ret = '';

        switch ($key) {
            case 'select':
                if (empty($stack['table'])) {
                    throw new QueryException('Table is not defined yet, call table() or from() to '
                        . 'set target table first');
                }

                if (isset($stack['select'])) {
                    $select = [];
                    foreach ($stack['select'] as $field) {
                        $select[] = $n . $ts . $field;
                    }

                    $ret .= $t . 'SELECT' . join(',', $select);
                    $ret .= $nt . 'FROM ' . $stack['table'];

                    isset($stack['join'])   && $ret .= $nt . $this->toQueryString('join', $indent);
                    isset($stack['where'])  && $ret .= $nt . $this->toQueryString('where', $indent);
                    isset($stack['group'])  && $ret .= $nt . $this->toQueryString('group', $indent);
                    isset($stack['having']) && $ret .= $nt . $this->toQueryString('having', $indent);
                    isset($stack['order'])  && $ret .= $nt . $this->toQueryString('order', $indent);
                    isset($stack['limit'])  && $ret .= $nt . $this->toQueryString('limit', $indent);

                    // Trim if no indent.
                    ($indent > 1) || $ret = trim($ret);
                }
                break;
            case 'where':
                if (isset($stack['where'])) {
                    $wheres = $stack['where'];
                    if (count($wheres) == 1) {
                        $ret = 'WHERE (' . $wheres[0][0] . ')';
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

                        $ret = $ws . str_repeat(')', $wsi); // Join & close parentheses.
                        $ret = ($indent > 1) ? 'WHERE (' . $n . $ts . $ret . $n . $t . ')'
                                             : 'WHERE (' . $ret . ')';
                    }
                }
                break;
            case 'insert':
                if (empty($stack['table'])) {
                    throw new QueryException('Table is not defined yet, call table() or from() to '
                        . 'set target table first');
                }

                if (isset($stack['insert'])) {
                    [$fields, $values] = $stack['insert'];

                    $ret = 'INSERT INTO ' . $stack['table']
                        . $nt . '(' . $fields . ')' . $n . 'VALUES'
                        . $nt . join(',' . $nt, $values);

                    if (isset($stack['conflict'])) {
                        ['fields' => $fields, 'action' => $action,
                         'update' => $update, 'where' => $where] = $stack['conflict'];

                        switch ($driver = $this->db->link()->pdoDriver()) {
                            case 'pgsql':
                                $ret .= $n . 'ON CONFLICT (' . $fields . ') DO ' . $action;
                                break;
                            case 'mysql':
                                $ret .= $n . 'ON DUPLICATE KEY ' . ($action = 'UPDATE');
                                break;
                            default:
                                throw new QueryException('Method conflict() available for PgSQL & MySQL only');
                        }

                        if ($action == 'UPDATE') {
                            $temp = (clone $this)->reset()->table('@');
                            $temp->update($update);

                            $ret .= ($driver == 'pgsql')
                                  ? $nt . 'SET ' . join(', ', $temp->stack['update'])
                                  : $nt . join(', ', $temp->stack['update']);

                            if ($where != null) {
                                @ [$where, $whereParams] = (array) $where;
                                $ret .= $nt . trim($temp->where((string) $where, (array) $whereParams)
                                    ->toQueryString('where'));
                            }

                            unset($temp);
                        }
                    }

                    if (isset($stack['return'])) {
                        $ret .= $n . 'RETURNING ' . $stack['return']['fields'];
                    }
                }
                break;
            case 'update':
                if (empty($stack['table'])) {
                    throw new QueryException('Table is not defined yet, call table() or from() to '
                        . 'set target table first');
                }

                if (isset($stack['update'])) {
                    if (empty($stack['where'])) {
                        throw new QueryException("No 'where' for 'update' yet, it must be provided for security "
                            . "reasons, call at least where('1=1') proving you're aware of what's going on");
                    }

                    $ret = 'UPDATE ' . $stack['table'] . $n . 'SET '
                         . join(', ' . $nt, $stack['update']);

                    isset($stack['where']) && $ret .= $n . $this->toQueryString('where', $indent);
                    isset($stack['order']) && $ret .= $n . $this->toQueryString('order', $indent);
                    isset($stack['limit']) && $ret .= $n . $this->toQueryString('limit', $indent);

                    if (isset($stack['return'])) {
                        $ret .= $n . 'RETURNING ' . $stack['return']['fields'];
                    }
                }
                break;
            case 'delete':
                if (empty($stack['table'])) {
                    throw new QueryException('Table is not defined yet, call table() or from() to '
                        . 'set target table first');
                }

                if (isset($stack['delete'])) {
                    if (empty($stack['where'])) {
                        throw new QueryException("No 'where' for 'update' yet, it must be provided for security "
                            . "reasons, call at least where('1=1') proving you're aware of what's going on");
                    }

                    $ret = 'DELETE FROM ' . $stack['table'];

                    isset($stack['where']) && $ret .= $n . $this->toQueryString('where', $indent);
                    isset($stack['order']) && $ret .= $n . $this->toQueryString('order', $indent);
                    isset($stack['limit']) && $ret .= $n . $this->toQueryString('limit', $indent);

                    if (isset($stack['return'])) {
                        $ret .= $n . 'RETURNING ' . $stack['return']['fields'];
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
                            throw new QueryException('No join context yet, use 2. argument of join() '
                                . 'or call on()/using() method');
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
     * Prepare.
     * @param  string     $in
     * @param  array|null $params
     * @return string
     * @throws froq\database\QueryException
     */
    public function prepare(string $in, array $params = null): string
    {
        $in = trim($in);

        if ($in === '') {
            throw new QueryException('Empty input given');
        }

        return $this->db->prepare($in, $params);
    }

    /**
     * Prepare field.
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

        if (!strpbrk($fields, ', ')) {
            return $this->db->escapeName($fields);
        }

        return $this->db->escapeNames($fields);
    }

    /**
     * Prepare op.
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

        throw new QueryException("Invalid op '%s', valids are: %s, 1, -1", [$op, join(', ', $ops)]);
    }

    /**
     * Prepare collation.
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
     * Prepare where in placeholders.
     * @param  array $fieldParams
     * @return string
     */
    private function prepareWhereInPlaceholders(array $fieldParams): string
    {
        return join(', ', array_fill(0, count($fieldParams), '?'));
    }

    /**
     * Prepare where like search.
     * @param  array $fieldParams
     * @return string
     * @throws froq\database\QueryException
     */
    private function prepareWhereLikeSearch(array $fieldParams): string
    {
        $count = count($fieldParams);
        if ($count == 1) {
            return $this->db->escapeLikeString($fieldParams[0]);
        }

        if ($count < 3) {
            throw new QueryException("Like parameters count must be 1 or 3, '%s' given", $count);
        }

        // Note to me..
        // 'foo%'  Anything starts with "foo"
        // '%foo'  Anything ends with "foo"
        // '%foo%' Anything have "foo" in any position
        // 'f_o%'  Anything have "o" in the second position
        // 'f_%_%' Anything starts with "f" and are at least 3 characters in length
        // 'f%o'   Anything starts with "f" and ends with "o"

        [$end, $search, $start] = $fieldParams;

        return $this->db->quote($start . $this->db->escapeLikeString($search, false) . $end);
    }

    /**
     * Has.
     * @param  string $key
     * @return bool
     */
    private function has(string $key): bool
    {
        return !empty($this->stack[$key]);
    }

    /**
     * Add a statement to stack.
     *
     * @param  string       $key
     * @param  string|array $value
     * @param  bool         $merge
     * @return self
     */
    private function add(string $key, $value, bool $merge = true): self
    {
        if ($merge) {
            $value = [...($this->stack[$key] ?? []), $value];
        }

        $this->key = $key; // Tick for last call.
        $this->stack[$key] = $value;

        return $this;
    }

    /**
     * Add a statement operator to stack.
     *
     * @param  string $key
     * @param  string $value
     * @return self
     * @throws froq\database\QueryException
     * @since  5.0
     */
    private function addTo(string $key, string $value): self
    {
        if (empty($this->stack[$key])) {
            $op = substr(trim($value), 0, strpos(trim($value), ' '));
            throw new QueryException("No '%s' statement yet in query stack to apply '%s' operator, "
                . "call %s() first to apply", [$key, $op, $key]);
        }

        $this->stack[$key][count($this->stack[$key]) - 1][1] = $value;

        return $this;
    }
}
