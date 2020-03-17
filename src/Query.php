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

use froq\pager\Pager;
use froq\database\{QueryException, Database, Result};
use froq\database\sql\{Sql, Name};

/**
 * Query.
 * @package froq\database
 * @object  froq\database\Query
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 */
final class Query
{
    /**
     * Db.
     * @var froq\database\Database
     */
    private Database $db;

    /**
     * Stack.
     * @var array
     */
    private array $stack = [];

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
     * Get db.
     * @return froq\database\Database
     */
    public function getDb(): Database
    {
        return $this->db;
    }

    /**
     * Db.
     * @aliasOf getDb()
     */
    public function db()
    {
        return $this->getDb();
    }

    /**
     * Table.
     * @param  string $table
     * @param  bool   $prepare
     * @return self
     */
    public function table(string $table, bool $prepare = true): self
    {
        if ($prepare) {
            $table = $this->prepareFields($table);
        }

        return $this->add('table', $table, false);
    }

    /**
     * From.
     * @aliasOf table()
     */
    public function from(...$arguments): self
    {
        return $this->table(...$arguments);
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
     * Select query.
     * @param  string|self $query
     * @param  string      $as
     * @return self
     * @throws froq\database\QueryException
     */
    public function selectQuery($query, string $as): self
    {
        if (!is_string($query) && !($query instanceof self)) {
            throw new QueryException('Invalid query type "%s", valids are: string, %s',
                [gettype($query), self::class]);
        }

        $select = trim((string) $query);
        if ($select == '') {
            throw new QueryException('Empty select query given');
        }

        return $this->select('('. $select .') AS '. $this->prepareField($as), false);
    }

    /**
     * Insert.
     * @param  array $data
     * @param  bool  $multi
     * @return self
     * @throws froq\database\QueryException
     */
    public function insert(array $data, bool $multi = false): self
    {
        $fields = $values = [];
        if (!$multi) {
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
        $fields      = $this->prepareFields(join(', ', $fields));
        foreach ($values as $i => $value) {
            $value = (array) $value;
            if (count($value) != $fieldsCount) {
                throw new QueryException('Count of value set "%s" not matched with fields count', [$i]);
            }
            $values[$i] = '('. join(', ', $this->db->escape($value)) .')';
        }

        return $this->add('insert', [$fields, $values], false);
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
            $set[] = $this->db->escapeName($name) .' = '. $this->db->escape($value);
        }

        return $this->add('update', join(', ', $set), false);
    }

    /**
     * Delete.
     * @return self
     */
    public function delete(): self
    {
        return $this->add('delete', '', false);
    }

    /**
     * Join.
     * @param  string      $to
     * @param  string      $on
     * @param  array|null  $onParams
     * @param  string|null $type
     * @return self
     */
    public function join(string $to, string $on, array $onParams = null, string $type = null): self
    {
        return $this->add('join', sprintf('%sJOIN %s ON %s',
            $type ? strtoupper($type) .' ' : '',
            $this->prepareFields($to),
            $this->prepare($on, $onParams)
        ));
    }

    /**
     * Join left.
     * @param  string     $to
     * @param  string     $on
     * @param  array|null $onParams
     * @return self
     */
    public function joinLeft(string $to, string $on, array $onParams = null): self
    {
        return $this->join($to, $on, $onParams, 'LEFT');
    }

    /**
     * Join right.
     * @param  string     $to
     * @param  string     $on
     * @param  array|null $onParams
     * @return self
     */
    public function joinRight(string $to, string $on, array $onParams = null): self
    {
        return $this->join($to, $on, $onParams, 'RIGHT');
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
        if ($whereParams) {
            $where = $this->prepare($where, $whereParams);
        }

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
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) .' = ?', (array) $fieldParam, $op);
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
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) .' != ?', (array) $fieldParam, $op);
    }

    /**
     * Where null.
     * @param  string      $field
     * @param  string|null $op
     * @return self
     */
    public function whereNull(string $field, string $op = null): self
    {
        return $this->where($this->prepareField($field) .' IS NULL', null, $op);
    }

    /**
     * Where not null.
     * @param  string      $field
     * @param  string|null $op
     * @return self
     */
    public function whereNotNull(string $field, string $op = null): self
    {
        return $this->where($this->prepareField($field) .' IS NOT NULL', null, $op);
    }

    /**
     * Where in.
     * @param  string      $field
     * @param  array       $fieldParams
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereIn(string $field, array $fieldParams, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryException('No parameters given');
        }

        return $this->where($this->prepareField($field)
            .' IN ('. $this->prepareWhereInPlaceholders($fieldParams) .')', $fieldParams, $op);
    }

    /**
     * Where not in.
     * @param  string      $field
     * @param  array       $fieldParams
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotIn(string $field, array $fieldParams, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryException('No parameters given');
        }

        return $this->where($this->prepareField($field)
            .' NOT IN ('. $this->prepareWhereInPlaceholders($fieldParams) .')', $fieldParams, $op);
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

        return $this->where($this->prepareField($field) .' BETWEEN ? AND ?', $fieldParams, $op);
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

        return $this->where($this->prepareField($field) .' NOT BETWEEN ? AND ?', $fieldParams, $op);
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
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) .' < ?', (array) $fieldParam, $op);
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
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) .' <= ?', (array) $fieldParam, $op);
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
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) .' > ?', (array) $fieldParam, $op);
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
        if (!$fieldParam) {
            throw new QueryException('No field parameter given');
        }

        return $this->where($this->prepareField($field) .' >= ?', (array) $fieldParam, $op);
    }

    /**
     * Where like.
     * @param  string      $field
     * @param  array       $fieldParams
     * @param  bool        $ilike
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereLike(string $field, array $fieldParams, bool $ilike = false, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryException('No parameters given');
        }

        [$field, $search] = [$this->prepareField($field), $this->prepareWhereLikeSearch($fieldParams)];

        if (!$ilike) {
            $where = $field .' LIKE '. $search;
        } else {
            $where = ($this->db->getLink()->getPdoDriver() == 'pgsql')
                ? sprintf('%s ILIKE %s', $field, $search)
                : sprintf('lower(%s) LIKE lower(%s)', $field, $search);
        }

        return $this->where($where, null, $op);
    }

    /**
     * Where not like.
     * @param  string      $field
     * @param  array       $fieldParams
     * @param  bool        $ilike
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryException
     */
    public function whereNotLike(string $field, array $fieldParams, bool $ilike = false, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryException('No parameters given');
        }

        [$field, $search] = [$this->prepareField($field), $this->prepareWhereLikeSearch($fieldParams)];

        if (!$ilike) {
            $where = $field .' NOT LIKE '. $search;
        } else {
            $where = ($this->db->getLink()->getPdoDriver() == 'pgsql')
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
        if ($queryParams) {
            $query = $this->prepare($query, $queryParams);
        }

        return $this->where('EXISTS ('. $query .')', null, $op);
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
        if ($queryParams) {
            $query = $this->prepare($query, $queryParams);
        }

        return $this->where('NOT EXISTS ('. $query .')', null, $op);
    }

    /**
     * Where random.
     * @param  float       $value
     * @param  string|null $op
     * @return self
     */
    public function whereRandom(float $value = 0.01, string $op = null): self
    {
        return ($this->db->getLink()->getPdoDriver() == 'pgsql')
            ? $this->where('random() < '. $value, $op)
            : $this->where('rand() < '. $value, $op);
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
     * @param  string $field
     * @return self
     */
    public function groupBy(string $field): self
    {
        return $this->add('groupBy', $this->prepareFields($field));
    }

    /**
     * Order.
     * @param  string|int|bool|null $field
     * @param  string               $op
     * @param  string|null          $collation
     * @return self
     */
    public function order(string $field, $op = null, string $collation = null): self
    {
        $op = $op ?? 'ASC';
        if (is_int($op) || is_bool($op)) {
            $op = ($op == 1) ? 'ASC' : 'DESC';
        }

        return $this->orderBy($field .' '. $op, $collation);
    }

    /**
     * Order by.
     * @param  string      $field
     * @param  string|null $collation
     * @return self
     */
    public function orderBy(string $field, string $collation = null): self
    {
        $field = trim($field);
        if ($collation) {
            $collation = ' COLLATE '. $this->prepareCollation($collation);
        }

        // Eg: "id ASC" or "id ASC, name DESC".
        if (strpos($field, ' ')) {
            $fields = [];
            foreach (explode(',', $field) as $i => $field) {
                @ [$field, $op] = explode(' ', trim($field));
                $fields[$i] = $this->prepareField($field) . $collation;
                if ($op) {
                    $fields[$i] .= ' '. $this->prepareOp($op);
                }
            }

            return $this->add('orderBy', implode(', ', $fields));
        }

        return $this->add('orderBy', $this->prepareField($field) . $collation);
    }

    /**
     * Order by random.
     * @return self
     */
    public function orderByRandom(): self
    {
        return ($this->db->getLink()->getPdoDriver() == 'pgsql')
            ? $this->add('orderBy', 'random()')
            : $this->add('orderBy', 'rand()');
    }

    /**
     * Limit.
     * @param  int      $limit
     * @param  int|null $offset
     * @return self
     */
    public function limit(int $limit, int $offset = null): self
    {
        return ($offset === null)
            ? $this->add('limit', abs($limit), false)
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
     * Or.
     * @return self
     */
    public function or(): self
    {
        if (isset($this->stack['where'])) {
            $this->stack['where'][count($this->stack['where']) - 1][1] = 'OR';
        }

        return $this;
    }

    /**
     * And.
     * @return self
     */
    public function and(): self
    {
        if (isset($this->stack['where'])) {
            $this->stack['where'][count($this->stack['where']) - 1][1] = 'AND';
        }

        return $this;
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
     * Equal.
     * @aliasOf whereEqual().
     */
    public function equal(...$arguments): self
    {
        return $this->whereEqual(...$arguments);
    }

    /**
     * Between.
     * @aliasOf whereBetween().
     */
    public function between(...$arguments): self
    {
        return $this->whereBetween(...$arguments);
    }

    /**
     * Asc.
     * @param  string      $field
     * @param  string|null $collation
     * @return self
     */
    public function asc(string $field = 'id', string $collation = null): self
    {
        return $this->orderBy($field .' ASC', $collation);
    }

    /**
     * Desc.
     * @param  string      $field
     * @param  string|null $collation
     * @return self
     */
    public function desc(string $field = 'id', string $collation = null): self
    {
        return $this->orderBy($field .' DESC', $collation);
    }

    /**
     * Run.
     * @param  array|null $fetchOptions
     * @return froq\database\Result
     */
    public function run(array $fetchOptions = null): Result
    {
        return $this->db->query($this->toString(), null, $fetchOptions);
    }

    /**
     * Get.
     * @param  array|null $fetchOptions
     * @return ?array|?object
     */
    public function get(array $fetchOptions = null)
    {
        return $this->db->get($this->toString(), null, $fetchOptions);
    }

    /**
     * Get all.
     * @param  array|null $fetchOptions
     * @return ?array
     */
    public function getAll(array $fetchOptions = null): ?array
    {
        return $this->db->getAll($this->toString(), null, $fetchOptions);
    }

    /**
     * Count.
     * @return int
     */
    public function count(): int
    {
        // Prevent empty query exception.
        $this->has('select') || $this->add('select', 1);

        return $this->db->countQuery($this->toString());
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
     * @param  froq\pager\Pager|null $pager
     * @return self
     */
    public function paginateWith(Pager $pager): self
    {
        return $this->limit($pager->getLimit(), $pager->getOffset());
    }

    /**
     * Name.
     * @param  string $input
     * @return froq\database\sql\Sql
     */
    public function sql(string $input): Sql
    {
        return new Sql($input);
    }

    /**
     * Name.
     * @param  string $input
     * @return froq\database\sql\Name
     */
    public function name(string $input): Name
    {
        return new Name($input);
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
     * @param  bool $pretty
     * @param  bool $sub
     * @return string
     * @throws froq\database\QueryException
     */
    public function toString(bool $pretty = false, bool $sub = false): string
    {
        $ret = '';

        if ($this->has('select')) {
            $ret = $this->toQueryString('select', $pretty, $sub);
        } elseif ($this->has('insert')) {
            $ret = $this->toQueryString('insert', $pretty, $sub);
        } elseif ($this->has('update')) {
            $ret = $this->toQueryString('update', $pretty, $sub);
        } elseif ($this->has('delete')) {
            $ret = $this->toQueryString('delete', $pretty, $sub);
        } else {
            throw new QueryException('No query ready to build, use select(), insert(), '.
                'update(), or delete() first');
        }

        return $ret;
    }

    /**
     * To query string.
     * @param  string $key
     * @param  bool   $pretty
     * @param  bool   $sub
     * @return string
     * @throws froq\database\QueryException
     */
    public function toQueryString(string $key, bool $pretty = false, bool $sub = false): string
    {
        $stack = $this->stack;
        if (empty($stack['table'])) {
            throw new QueryException('Table is not defined yet, call table() or from() to '.
                'set target table first.');
        }

        $n = $t = $nt = $ns = ' ';
        if ($pretty) {
            $n = "\n"; $t = "\t";
            $nt = "\n\t"; $ns = "\n";
            if ($sub) {
                $nt = "\n\t\t"; $ns = "\n\t";
            }
        }

        $ret = '';

        switch ($key) {
            case 'select':
                $select = $stack['select'];

                $ret  = sprintf('SELECT%s%s%sFROM ', $nt, join(','. $nt, $select), $ns);
                $ret .= trim(
                      $this->toQueryString('table', $pretty, $sub)
                    . $this->toQueryString('join', $pretty, $sub)
                    . $this->toQueryString('where', $pretty, $sub)
                    . $this->toQueryString('groupBy', $pretty, $sub)
                    . $this->toQueryString('having', $pretty, $sub)
                    . $this->toQueryString('orderBy', $pretty, $sub)
                    . $this->toQueryString('limit', $pretty, $sub)
                );

                if ($sub) {
                    $ret = $ns . $ret . $n;
                }
                break;
            case 'table':
                $table = $stack['table'];
                // Could not remove after writing.. :(
                // if ($pretty && strpos($table, "\n") > -1) {
                //     $lines = explode("\n", $table);
                //     $lines = array_map(function ($line) use ($t) {
                //         if ($line = trim($line)) {
                //             switch ($line) {
                //                 case '(': break;
                //                 case ')': break;
                //                  default:
                //                     $line = ($line[0] == ')')
                //                         ? $t . $line : $t . $t . $line;
                //             }
                //         }
                //         return $line;
                //     }, $lines);
                //     $table = implode("\n", $lines);
                // }
                $ret = $table;
                break;
            case 'where':
                if (isset($stack['where'])) {
                    $wheres = $stack['where'];
                    if (count($wheres) == 1) {
                        $ret = $ns .'WHERE '. $wheres[0][0];
                    } else {
                        $ws = ''; $wsi = 0;
                        foreach ($wheres as $i => [$where, $op]) {
                            $nx   = ($wheres[$i + 1] ?? null);
                            $nxnx = ($wheres[$i + 2] ?? null);
                            $nxop = ($nx[1] ?? '');

                            $ws .= $where;
                            if ($nx) {
                                $ws .= ' '. $op .' ';
                            }
                            if ($op != $nxop && $nxop && $nxnx) {
                                $ws .= '(';
                                $wsi++;
                            }
                        }

                        $ret = $ws . str_repeat(')', $wsi); // Join & close parentheses.
                        $ret = $ns .'WHERE ('. $nt . $ret . $ns .')';
                    }
                }
                break;
            case 'insert':
                if (isset($stack['insert'])) {
                    [$fields, $values] = $stack['insert'];

                    $ret = "INSERT INTO {$stack['table']} {$nt}({$fields}) {$n}VALUES {$nt}"
                        . join(','. ($nt ?: $ns), $values);
                }
                break;
            case 'update':
                if (isset($stack['update'])) {
                    if (!isset($stack['where'])) {
                        throw new QueryException('No "where" for %s yet, it must be provided for '.
                            'security reasons at least "1=1" that proves you’re aware of what’s going on', [$key]);
                    }

                    $ret = trim(
                        "UPDATE {$stack['table']} SET {$nt}". $stack['update']
                        . $this->toQueryString('where', $pretty, $sub)
                        . $this->toQueryString('orderBy', $pretty, $sub)
                        . $this->toQueryString('limit', $pretty, $sub)
                    );
                }
                break;
            case 'delete':
                if (isset($stack['delete'])) {
                    if (!isset($stack['where'])) {
                        throw new QueryException('No "where" for %s yet, it must be provided for '.
                            'security reasons at least "1=1" that proves you’re aware of what’s going on', [$key]);
                    }

                    $ret = trim(
                        "DELETE FROM {$stack['table']}"
                        . $this->toQueryString('where', $pretty, $sub)
                        . $this->toQueryString('orderBy', $pretty, $sub)
                        . $this->toQueryString('limit', $pretty, $sub)
                    );
                }
                break;
            case 'groupBy':
                if (isset($stack['groupBy'])) {
                    $ret = $ns .'GROUP BY '. join(', ', $stack['groupBy']);
                }
                break;
            case 'orderBy':
                if (isset($stack['orderBy'])) {
                    $ret = $ns .'ORDER BY '. join(', ', $stack['orderBy']);
                }
                break;
            case 'limit':
                if (isset($stack['limit'])) {
                    $ret = isset($stack['offset'])
                        ? $ns .'LIMIT '. $stack['limit'] .' OFFSET '. $stack['offset']
                        : $ns .'LIMIT '. $stack['limit'];
                }
                break;
            case 'join':
                if (isset($stack['join'])) {
                    foreach ($stack['join'] as $join) {
                        $ret .= $ns . $join;
                    }
                }
                break;
            case 'having':
                if (isset($stack['having'])) {
                    $ret = $ns .'HAVING ('. $stack['having'] .')';
                }
                break;
        }

        return $ret;
    }

    /**
     * Prepare.
     * @param  string     $input
     * @param  array|null $inputParams
     * @return string
     * @throws froq\database\QueryException
     */
    public function prepare(string $input, array $inputParams = null): string
    {
        $input = trim($input);

        if ($input === '') {
            throw new QueryException('Empty input given');
        }

        return $this->db->prepare($input, $inputParams);
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
     * @return string
     * @throws froq\database\QueryException
     */
    public function prepareOp(string $op): string
    {
        static $ops = ['OR', 'AND', 'ASC', 'DESC'];

        $op = strtoupper($op);
        if (in_array($op, $ops)) {
            return $op;
        }

        throw new QueryException('Invalid op "%s", valids are: %s', [$op, join(', ', $ops)]);
    }

    /**
     * Prepare collation.
     * @param  string $collation
     * @return string
     */
    public function prepareCollation(string $collation): string
    {
        $collation = trim($collation);

        if ($this->db->getLink()->getPdoDriver() == 'pgsql') {
            $collation = '"'. trim($collation, '"') .'"';
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
            throw new QueryException('Like parameters count must be 1 or 3, %s given', [$count]);
        }

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
        return isset($this->stack[$key]);
    }

    /**
     * Add.
     * @param string $key
     * @param any    $value
     * @param bool   $merge
     */
    private function add(string $key, $value, bool $merge = true): self
    {
        $this->stack[$key] = $merge ? [...$this->stack[$key] ?? [], $value] : $value;

        return $this;
    }
}