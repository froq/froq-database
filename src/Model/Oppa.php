<?php
/**
 * Copyright (c) 2016 Kerem Güneş
 *     <k-gun@mail.com>
 *
 * GNU General Public License v3.0
 *     <http://www.gnu.org/licenses/gpl-3.0.txt>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace Froq\Database\Model;

use Oppa\Query\Builder as QueryBuilder;

/**
 * @package    Froq
 * @subpackage Froq\Database\Model
 * @object     Froq\Database\Model\Oppa
 * @author     Kerem Güneş <k-gun@mail.com>
 */
class Oppa extends Model
{
    /**
     * Constructor.
     */
    final public function __construct()
    {
        $this->db = app('db')->initOppa();

        parent::__construct();
    }

    /**
     * Find.
     * @param  any $pv
     * @return any
     */
    public function find($pv = null)
    {
        $pn = $this->getStackPrimary();
        if (!$pn) {
            throw new ModelException('Stack primary is not defined!');
        }

        $pv = $pv ?? $this->getStackPrimaryValue();
        if ($pv === null) {
            return;
        }

        try {
            return $this->queryBuilder()->select('*')->whereEqual($pn, $pv)->limit(1)->get();
        } catch (\Exception $e) {
            $this->setFail($e);
        }
    }

    /**
     * Find all.
     * @param  string|null $where
     * @param  array|null  $whereParams
     * @param  int         $limit
     * @param  int         $order
     * @return any
     */
    public function findAll(string $where = null, array $whereParams = null, int $limit = null,
        int $order = -1)
    {
        $pn = $this->getStackPrimary();
        if (!$pn) {
            throw new ModelException('Stack primary is not defined!');
        }

        try {
            $query = $this->queryBuilder();
            $query->select('*');

            if ($where) {
                $query->where($where, $whereParams);
            }

            if ($order == -1) {
                $query->orderBy($pn, QueryBuilder::OP_DESC);
            } elseif ($order == 1) {
                $query->orderBy($pn, QueryBuilder::OP_ASC);
            }

            if ($limit != -1) { // no pager?
                if ($limit === null) {
                    // paginate
                    list($start, $stop) = $this->pager->run($query->count());
                    if ($start || $stop) {
                        $query->limit($start, $stop);
                    }
                } else {
                    $query->limit($limit);
                }
            }

            return $query->getAll();
        } catch (\Exception $e) {
            $this->setFail($e);
        }
    }

    /**
     * Find by.
     * @param  string $field
     * @param  any    $fieldParam
     * @param  int    $order
     * @return any
     */
    public function findBy(string $field, $fieldParam, int $order = -1)
    {
        return $this->findAll($field .' = ?', [$fieldParam], 1, $order)[0] ?? null;
    }

    /**
     * Find by all.
     * @param  string     $field
     * @param  array|null $fieldParam
     * @param  int|null   $limit
     * @param  int        $order
     * @return any
     */
    public function findByAll(string $field, array $fieldParam = null, int $limit = null,
        int $order = -1)
    {
        return $this->findAll($field .' = ?', [$fieldParam], $limit, $order);
    }

    /**
     * Save an object.
     * @return int|null
     */
    public function save()
    {
        $batch = null;
        $agent = $this->db->getLink()->getAgent();
        if ($this->usesTransaction()) {
            $batch = $agent->getBatch();
            $batch->lock();
        }

        // create query builder
        $query = $this->queryBuilder();

        $return = null;
        try {
            $data = $this->data->toArray();

            $pv = $this->getStackPrimaryValue();
            if (!$pv) { // insert
                $query = $query->insert($data)->toString();
            } else {    // update
                $pn = $this->getStackPrimary();
                if (!$pn) {
                    throw new ModelException('Stack primary is not defined!');
                }

                // drop primary name
                unset($data[$pn]);

                $query = $query->update($data)->whereEqual($pn, $pv)->toString();
            }

            if ($batch) {
                $result = $batch->doQuery($query)->getResult();
            } else {
                $result = $agent->query($query);
            }

            // free data
            $this->reset();

            // set return
            if ($pv) {
                $return = $result ? $result->getRowsAffected() : 0;
            } else {
                // set with new id
                $result && $this->setStackPrimaryValue($return = $result->getId());
            }
        } catch (\Exception $e) {
            $this->setFail($e);

            // rollback
            $batch && $batch->undo();
        }

        $batch && $batch->unlock();

        return $return;
    }

    /**
     * Remove.
     * @return int|bool
     */
    public function remove()
    {
        $pn = $this->getStackPrimary();
        if (!$pn) {
            throw new ModelException('Stack primary is not defined!');
        }

        $pv = $this->getStackPrimaryValue();
        if (!$pv) {
            return false;
        }

        $batch = null;
        $agent = $this->db->getLink()->getAgent();
        if ($this->usesTransaction()) {
            $batch = $agent->getBatch();
            $batch->lock();
        }

        // create query builder
        $query = $this->queryBuilder();

        $return = false;
        try {
            $query = $query->delete()->whereEqual($pn, $pv)->toString();

            if ($batch) {
                $result = $batch->doQuery($query)->getResult();
            } else {
                $result = $agent->query($query);
            }

            // set return
            $return = $result ? $result->getRowsAffected() : 0;
        } catch (\Exception $e) {
            $this->setFail($e);

            // rollback
            $batch && $batch->undo();
        }

        $batch && $batch->unlock();

        return $return;
    }

    /**
     * Count.
     * @param  string|null $where
     * @param  array|null  $whereParams
     * @return int
     */
    public function count(string $where = null, array $whereParams = null): int
    {
        try {
            $query = $this->queryBuilder();
            $query->select('1');

            if ($where) {
                $query->where($where, $whereParams);
            }

            return $query->count();
        } catch (\Exception $e) {
            $this->setFail($e);

            return -1;
        }
    }

    /**
     * New query builder.
     * @param  string|null $stack
     * @return Oppa\Query\Builder
     */
    final public function queryBuilder(string $stack = null): QueryBuilder
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setLink($this->db->getLink());

        // use self name
        $stack =  $stack ?: $this->stack;

        if (!$stack) {
            throw new ModelException('Stack is not defined!');
        }

        $queryBuilder->setTable($stack);

        return $queryBuilder;
    }
}
