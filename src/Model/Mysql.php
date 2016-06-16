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
 * @object     Froq\Database\Model\Mysql
 * @author     Kerem Güneş <k-gun@mail.com>
 */
class Mysql extends Model
{
    /**
     * Constructor.
     */
    final public function __construct()
    {
        $this->db = app('db')->initMysql();

        parent::__construct();
    }

    /**
     * Find.
     * @param  any $id
     * @return any
     */
    public function find($id = null)
    {
        $id = $id ?? $this->getStackPrimaryValue();
        if ($id === null) {
            return;
        }

        try {
            return $this->queryBuilder()
                ->select('*')->whereEqual($this->stackPrimary, $id)->limit(1)->get();
        } catch (\Throwable $e) {
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
    public function findAll(string $where = null, array $whereParams = null,
        int $limit = null, int $order = -1)
    {
        try {
            $query = $this->queryBuilder();
            $query->select('*');

            // where
            if ($where) {
                $query->where($where, $whereParams);
            }

            // order
            if ($order == -1) {
                $query->orderBy($this->stackPrimary, QueryBuilder::OP_DESC);
            } elseif ($order == 1) {
                $query->orderBy($this->stackPrimary, QueryBuilder::OP_ASC);
            }

            // paginate
            if ($limit === null) {
                list($start, $stop) = $this->pager->run($query->count());
                if ($start || $stop) {
                    $query->limit($start, $stop);
                }
            } else {
                $query->limit($limit);
            }

            return $query->getAll();
        } catch (\Throwable $e) {
            $this->setFail($e);
        }
    }

    /**
     * Save an object.
     * @return int|null
     */
    public function save()
    {
        $agent = $this->db->getLink()->getAgent();
        $batch = null;
        if ($this->useTransaction) {
            $batch = $agent->getBatch();
            $batch->lock();
        }

        // create query builder
        $query = $this->queryBuilder();

        $return = null;
        try {
            $id = $this->getStackPrimaryValue();
            if (!$id) { // insert
                $query->insert($this->data->toArray());
            } else {    // update
                $query->update($this->data->toArray())->whereEqual($this->stackPrimary, $id);
            }

            if ($this->useTransaction) {
                $result = $batch->queue($query->toString())->run()->getResult()[0] ?? null;
            } else {
                $result = $agent->query($query->toString());
            }

            // set return
            if ($id) {
                $return = $result ? $result->getRowsAffected() : 0;
            } else {
                // set with new id
                $result && $this->setStackPrimaryValue($return = $result->getId());
            }
        } catch (\Throwable $e) {
            $this->setFail($e);

            // rollback
            $batch && $batch->cancel();
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
        $id = $this->getStackPrimaryValue();
        if (!$id) {
            return false;
        }

        $agent = $this->db->getLink()->getAgent();
        $batch = null;
        if ($this->useTransaction) {
            $batch = $agent->getBatch();
            $batch->lock();
        }

        // create query builder
        $query = $this->queryBuilder();

        $return = false;
        try {
            $query->delete()->whereEqual($this->stackPrimary, $id);

            if ($this->useTransaction) {
                $result = $batch->queue($query->toString())->run()->getResult()[0] ?? null;
            } else {
                $result = $agent->query($query->toString());
            }

            // set return
            $return = $result ? $result->getRowsAffected() : 0;
        } catch (\Throwable $e) {
            $this->setFail($e);

            // rollback
            $batch && $batch->cancel();
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
        } catch (\Throwable $e) {
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
        if ($stack == '') {
            $stack = $this->stack;
        }

        $queryBuilder->setTable($stack);

        return $queryBuilder;
    }
}
