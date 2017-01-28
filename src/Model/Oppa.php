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
     * @param  any $id
     * @return any
     */
    public function find($id = null)
    {
        $idn = $this->getStackPrimary();
        if (!$idn) {
            throw new ModelException('Stack primary is not defined!');
        }

        $idv = $idv ?? $this->getStackPrimaryValue();
        if ($idv === null) {
            return;
        }

        try {
            return $this->queryBuilder()->select('*')->whereEqual($idn, $id)->limit(1)->get();
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
    public function findAll(string $where = null, array $whereParams = null, int $limit = null,
        int $order = -1)
    {
        $idn = $this->getStackPrimary();
        if (!$idn) {
            throw new ModelException('Stack primary is not defined!');
        }

        try {
            $query = $this->queryBuilder();
            $query->select('*');

            if ($where) {
                $query->where($where, $whereParams);
            }

            if ($order == -1) {
                $query->orderBy($idn, QueryBuilder::OP_DESC);
            } elseif ($order == 1) {
                $query->orderBy($idn, QueryBuilder::OP_ASC);
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
            $idv = $this->getStackPrimaryValue();
            if (!$idv) { // insert
                $query = $query->insert($this->data->toArray())->toString();
            } else {     // update
                $idn = $this->getStackPrimary();
                if (!$idn) {
                    throw new ModelException('Stack primary is not defined!');
                }
                $query = $query->update($this->data->toArray())->whereEqual($idn, $idv)->toString();
            }

            if ($batch) {
                $result = $batch->runQuery($query)->getResult();
            } else {
                $result = $agent->query($query);
            }

            // set return
            if ($idv) {
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
        $idn = $this->getStackPrimary();
        if (!$idn) {
            throw new ModelException('Stack primary is not defined!');
        }

        $idv = $this->getStackPrimaryValue();
        if (!$idv) {
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
            $query = $query->delete()->whereEqual($idn, $idv)->toString();

            if ($batch) {
                $result = $batch->runQuery($query)->getResult();
            } else {
                $result = $agent->query($query);
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
        $stack =  $stack ?: $this->stack;

        if (!$stack) {
            throw new ModelException('Stack is not defined!');
        }

        $queryBuilder->setTable($stack);

        return $queryBuilder;
    }
}
