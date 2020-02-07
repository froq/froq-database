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

use froq\database\{QueryBuilderException, Database, Result};
use froq\database\sql\{Sql, Name};

/**
 * Query Builder.
 * @package froq\database
 * @object  froq\database\QueryBuilder
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 */
final class QueryBuilder
{
    /**
     * Db.
     * @var froq\database\Database
     */
    private Database $db;

    /**
     * Query.
     * @var array
     */
    private array $query = [];

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
     * Get query.
     * @return array
     */
    public function getQuery(): array
    {
        return $this->query;
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
     */
    public function select(string $select, bool $prepare = true): self
    {
        if ($prepare) {
            $select = $this->prepareFields($select);
        }

        return $this->add('select', $select);
    }

    /**
     * Insert.
     * @param  array $data
     * @param  bool  $multi
     * @return self
     * @throws froq\database\QueryBuilderException
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
            throw new QueryBuilderException('Both fields & values should not be empty for insert');
        }

        $fieldsCount = count($fields);
        $fields      = $this->prepareFields(join(', ', $fields));
        foreach ($values as $i => $value) {
            $value = (array) $value;
            if (count($value) != $fieldsCount) {
                throw new QueryBuilderException('Count of value set %s is not matching with fields count', [$i]);
            }
            $values[$i] = '('. join(', ', $this->db->escape($value)) .')';
        }

        return $this->add('insert', [$fields, $values], false);
    }

    /**
     * Update.
     * @param  array $data
     * @return self
     */
    public function update(array $data): self
    {
        if (!$data) {
            throw new QueryBuilderException('Empty data given for update');
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
        return $this->add('join', sprintf('%sJOIN %s ON (%s)',
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
     * @param  array       $fieldParam
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryBuilderException
     */
    public function whereEqual(string $field, array $fieldParam, string $op = null): self
    {
        if (!$fieldParam) {
            throw new QueryBuilderException('No parameter given');
        }

        return $this->where($field .' = ?', $fieldParam, $op);
    }

    /**
     * Where not equal.
     * @param  string      $field
     * @param  array       $fieldParam
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryBuilderException
     */
    public function whereNotEqual(string $field, array $fieldParam, string $op = null): self
    {
        if (!$fieldParam) {
            throw new QueryBuilderException('No parameter given');
        }

        return $this->where($field .' != ?', $fieldParam, $op);
    }

    /**
     * Where null.
     * @param  string      $field
     * @param  string|null $op
     * @return self
     */
    public function whereNull(string $field, string $op = null): self
    {
        return $this->where($field .' IS NULL', null, $op);
    }

    /**
     * Where not null.
     * @param  string      $field
     * @param  string|null $op
     * @return self
     */
    public function whereNotNull(string $field, string $op = null): self
    {
        return $this->where($field .' IS NOT NULL', null, $op);
    }

    /**
     * Where in.
     * @param  string      $field
     * @param  array       $fieldParams
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryBuilderException
     */
    public function whereIn(string $field, array $fieldParams, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryBuilderException('No parameters given');
        }

        $placeholders = $this->prepareWhereInPlaceholders($fieldParams);

        return $this->where($field .' IN ('. $placeholders .')', $fieldParams, $op);
    }

    /**
     * Where not in.
     * @param  string      $field
     * @param  array       $fieldParams
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryBuilderException
     */
    public function whereNotIn(string $field, array $fieldParams, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryBuilderException('No parameters given');
        }

        $placeholders = $this->prepareWhereInPlaceholders($fieldParams);

        return $this->where($field .' NOT IN ('. $placeholders .')', $fieldParams, $op);
    }

    /**
     * Where between.
     * @param  string      $field
     * @param  array       $fieldParams
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryBuilderException
     */
    public function whereBetween(string $field, array $fieldParams, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryBuilderException('No parameters given');
        }

        return $this->where($field .' BETWEEN ? AND ?', $fieldParams, $op);
    }

    /**
     * Where not between.
     * @param  string      $field
     * @param  array       $fieldParams
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryBuilderException
     */
    public function whereNotBetween(string $field, array $fieldParams, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryBuilderException('No parameters given');
        }

        return $this->where($field .' NOT BETWEEN ? AND ?', $fieldParams, $op);
    }

    /**
     * Where less than.
     * @param  string      $field
     * @param  array       $fieldParam
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryBuilderException
     */
    public function whereLessThan(string $field, array $fieldParam, string $op = null): self
    {
        if (!$fieldParam) {
            throw new QueryBuilderException('No parameter given');
        }

        return $this->where($field .' < ?', $fieldParam, $op);
    }

    /**
     * Where less than equal.
     * @param  string      $field
     * @param  array       $fieldParam
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryBuilderException
     */
    public function whereLessThanEqual(string $field, array $fieldParam, string $op = null): self
    {
        if (!$fieldParam) {
            throw new QueryBuilderException('No parameter given');
        }

        return $this->where($field .' <= ?', $fieldParam, $op);
    }

    /**
     * Where greater than.
     * @param  string      $field
     * @param  array       $fieldParam
     * @param  string|null $op
     * @return self
     */
    public function whereGreaterThan(string $field, array $fieldParam, string $op = null): self
    {
        if (!$fieldParam) {
            throw new QueryBuilderException('No parameter given');
        }

        return $this->where($field .' > ?', $fieldParam, $op);
    }

    /**
     * Where greater than equal.
     * @param  string      $field
     * @param  array       $fieldParam
     * @param  string|null $op
     * @return self
     */
    public function whereGreaterThanEqual(string $field, array $fieldParam, string $op = null): self
    {
        if (!$fieldParam) {
            throw new QueryBuilderException('No parameter given');
        }

        return $this->where($field .' >= ?', $fieldParam, $op);
    }

    /**
     * Where like.
     * @param  string      $field
     * @param  array       $fieldParams
     * @param  bool        $ilike
     * @param  string|null $op
     * @return self
     * @throws froq\database\QueryBuilderException
     */
    public function whereLike(string $field, array $fieldParams, bool $ilike = false, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryBuilderException('No parameters given');
        }

        [$field, $search] = [$this->prepareField($field), $this->prepareWhereLikeSearch($fieldParams)];

        $where = $field .' LIKE '. $search;
        if ($ilike) {
            $where = $this->db->getLink()->getPdoDriver() == 'pgsql'
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
     * @throws froq\database\QueryBuilderException
     */
    public function whereNotLike(string $field, array $fieldParams, bool $ilike = false, string $op = null): self
    {
        if (!$fieldParams) {
            throw new QueryBuilderException('No parameters given');
        }

        [$field, $search] = [$this->prepareField($field), $this->prepareWhereLikeSearch($fieldParams)];

        $where = $field .' NOT LIKE '. $search;
        if ($ilike) {
            $where = $this->db->getLink()->getPdoDriver() == 'pgsql'
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
     * Order by.
     * @param  string $field
     * @return self
     */
    public function orderBy(string $field): self
    {
        $field = trim($field);

        // Eg: "id ASC" or "id ASC, name DESC".
        if (strpos($field, ' ')) {
            $fields = [];
            foreach (explode(',', $field) as $i => $field) {
                @ [$field, $op] = explode(' ', trim($field));
                $fields[$i] = $this->prepareField($field);
                if ($op) {
                    $fields[$i] .= ' '. $this->prepareOp($op);
                }
            }

            return $this->add('orderBy', implode(', ', $fields));
        }

        return $this->add('orderBy', $this->prepareField($field));
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
     * @throws froq\database\QueryBuilderException
     */
    public function offset(int $offset): self
    {
        if (!$this->has('limit')) {
            throw new QueryBuilderException('Limit not set yet, call limit() first for offset');
        }

        return $this->add('offset', abs($offset), false);
    }

    /**
     * Or.
     * @return self
     */
    public function or(): self
    {
        if (isset($this->query['where'])) {
            $this->query['where'][count($this->query['where']) - 1][1] = 'OR';
        }

        return $this;
    }

    /**
     * And.
     * @return self
     */
    public function and(): self
    {
        if (isset($this->query['where'])) {
            $this->query['where'][count($this->query['where']) - 1][1] = 'AND';
        }

        return $this;
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
     * @return self
     */
    public function paginate(): self
    {
        $pager = $this->db->initPager($this->count());

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
        $this->query = [];

        return $this;
    }

    /**
     * To array.
     * @return array
     */
    public function toArray(): array
    {
        return $this->query;
    }

    /**
     * To string.
     * @param  bool $pretty
     * @param  bool $sub
     * @return string
     * @throws froq\database\QueryBuilderException
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
            throw new QueryBuilderException('No query ready to build, use select(), insert(), '.
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
     * @throws froq\database\QueryBuilderException
     */
    public function toQueryString(string $key, bool $pretty = false, bool $sub = false): string
    {
        $query = $this->query;
        if (empty($query['table'])) {
            throw new QueryBuilderException('Table is not defined yet, call table() or from() to '.
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
                $select = $query['select'];

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
                $table = $query['table'];
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
                if (isset($query['where'])) {
                    $wheres = $query['where'];
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
                if (isset($query['insert'])) {
                    [$fields, $values] = $query['insert'];

                    $ret = "INSERT INTO {$query['table']} {$nt}({$fields}) {$n}VALUES {$nt}"
                        . join(','. ($nt ?: $ns), $values);
                }
                break;
            case 'update':
                if (isset($query['update'])) {
                    if (!isset($query['where'])) {
                        throw new QueryBuilderException('No "where" for %s yet, it must be provided for '.
                            'security reasons at least "1=1" that proves you’re aware of what’s going on', [$key]);
                    }

                    $ret = trim(
                        "UPDATE {$query['table']} SET {$nt}". $query['update']
                        . $this->toQueryString('where', $pretty, $sub)
                        . $this->toQueryString('orderBy', $pretty, $sub)
                        . $this->toQueryString('limit', $pretty, $sub)
                    );
                }
                break;
            case 'delete':
                if (isset($query['delete'])) {
                    if (!isset($query['where'])) {
                        throw new QueryBuilderException('No "where" for %s yet, it must be provided for '.
                            'security reasons at least "1=1" that proves you’re aware of what’s going on', [$key]);
                    }

                    $ret = trim(
                        "DELETE FROM {$query['table']}"
                        . $this->toQueryString('where', $pretty, $sub)
                        . $this->toQueryString('orderBy', $pretty, $sub)
                        . $this->toQueryString('limit', $pretty, $sub)
                    );
                }
                break;
            case 'groupBy':
                if (isset($query['groupBy'])) {
                    $ret = $ns .'GROUP BY '. join(', ', $query['groupBy']);
                }
                break;
            case 'orderBy':
                if (isset($query['orderBy'])) {
                    $ret = $ns .'ORDER BY '. join(', ', $query['orderBy']);
                }
                break;
            case 'limit':
                if (isset($query['limit'])) {
                    $ret = isset($query['offset'])
                        ? $ns .'LIMIT '. $query['limit'] .' OFFSET '. $query['offset']
                        : $ns .'LIMIT '. $query['limit'];
                }
                break;
            case 'join':
                if (isset($query['join'])) {
                    foreach ($query['join'] as $join) {
                        $ret .= $ns . $join;
                    }
                }
                break;
            case 'having':
                if (isset($query['having'])) {
                    $ret = $ns .'HAVING ('. $query['having'] .')';
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
     * @throws froq\database\QueryBuilderException
     */
    public function prepare(string $input, array $inputParams = null): string
    {
        $input = trim($input);

        if ($input === '') {
            throw new QueryBuilderException('Empty input given');
        }

        return $this->db->prepare($input, $inputParams);
    }

    /**
     * Prepare field.
     * @param  string $field
     * @return string
     * @throws froq\database\QueryBuilderException
     */
    public function prepareField(string $field): string
    {
        $field = trim($field);

        if ($field === '') {
            throw new QueryBuilderException('Empty field given');
        }

        return $this->db->escapeName($field);
    }

    /**
     * Prepare fields.
     * @param  string $fields
     * @return string
     * @throws froq\database\QueryBuilderException
     */
    public function prepareFields(string $fields): string
    {
        $fields = trim($fields);

        if ($fields === '') {
            throw new QueryBuilderException('Empty fields given');
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
     * @throws froq\database\QueryBuilderException
     */
    public function prepareOp(string $op): string
    {
        static $ops = ['OR', 'AND', 'ASC', 'DESC'];

        $op = strtoupper($op);
        if (in_array($op, $ops)) {
            return $op;
        }

        throw new QueryBuilderException('Invalid op "%s", valids are "%s"', [$op, join(', ', $ops)]);
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
     * @throws froq\database\QueryBuilderException
     */
    private function prepareWhereLikeSearch(array $fieldParams): string
    {
        $count = count($fieldParams);
        if ($count == 1) {
            return $this->db->escapeLikeString($fieldParams[0]);
        }

        if ($count < 3) {
            throw new QueryBuilderException('Like parameters count must be 1 or 3, %s given', [$count]);
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
        return isset($this->query[$key]);
    }

    /**
     * Add.
     * @param string $key
     * @param any    $value
     * @param bool   $merge
     */
    private function add(string $key, $value, bool $merge = true): self
    {
        $this->query[$key] = $merge ? [...$this->query[$key] ?? [], $value] : $value;

        return $this;
    }
}
