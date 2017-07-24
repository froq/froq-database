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
     * @inheritDoc Froq\Database\Model\Model
     */
    final public function __construct()
    {
        parent::__construct();
    }

    /**
     * @inheritDoc Froq\Database\Model\ModelInterface
     */
    public function query(string $query, array $queryParams = null)
    {
        try {
            return $this->vendor->getLink()->getAgent()->query($query, $queryParams);
        } catch (\Exception $e) {
            $this->setFail($e);
        }
    }

    /**
     * @inheritDoc Froq\Database\Model\ModelInterface
     */
    public function find($pv = null)
    {
        $pn = $this->getStackPrimary();
        $pv = $pv ?? $this->getStackPrimaryValue();
        if ($pv === null) {
            return;
        }

        try {
            return $this->initQueryBuilder()->select('*')->whereEqual($pn, $pv)->limit(1)->get();
        } catch (\Exception $e) {
            $this->setFail($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function findAll(string $where = null, array $whereParams = null, int $limit = null, int $order = 1)
    {
        $pn = $this->getStackPrimary();

        try {
            $query = $this->initQueryBuilder();
            $query->select('*');

            if ($where) {
                $query->where($where, $whereParams);
            }

            if ($order == 1) {
                $query->orderBy($pn, QueryBuilder::OP_ASC);
            } elseif ($order == -1) {
                $query->orderBy($pn, QueryBuilder::OP_DESC);
            }

            if ($limit === null) { // null => paginate
                list($start, $stop) = $this->pager->run($query->count());
                if ($start || $stop) {
                    $query->limit($start, $stop);
                }
            } elseif ($limit != -1) { // -1 => no limit
                $query->limit($limit);
            }

            return $query->getAll();
        } catch (\Exception $e) {
            $this->setFail($e);
        }
    }

    /**
     * @inheritDoc Froq\Database\Model\ModelInterface
     */
    public function save()
    {
        $batch = null;
        $agent = $this->vendor->getLink()->getAgent();
        if ($this->usesTransaction()) {
            $batch = $agent->getBatch();
            $batch->lock();
        }

        // create query builder
        $query = $this->initQueryBuilder();

        $return = null;
        try {
            $data = $this->data->toArray();

            $pv = $this->getStackPrimaryValue();
            if (!$pv) { // insert
                $query = $query->insert($data)->toString();
            } else {    // update
                $pn = $this->getStackPrimary();

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
     * @inheritDoc Froq\Database\Model\ModelInterface
     */
    public function remove()
    {
        $pn = $this->getStackPrimary();
        $pv = $this->getStackPrimaryValue();
        if (!$pv) {
            return null;
        }

        $batch = null;
        $agent = $this->vendor->getLink()->getAgent();
        if ($this->usesTransaction()) {
            $batch = $agent->getBatch();
            $batch->lock();
        }

        // create query builder
        $query = $this->initQueryBuilder();

        $return = null;
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
     * @inheritDoc Froq\Database\Model\ModelInterface
     */
    public function count(string $where = null, array $whereParams = null): int
    {
        try {
            $query = $this->initQueryBuilder();
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
     * Init query builder.
     * @param  string|null $stack
     * @return Oppa\Query\Builder
     */
    final public function initQueryBuilder(string $stack = null): QueryBuilder
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setLink($this->vendor->getLink());

        // use self name
        $stack =  $stack ?: $this->stack;

        if (!$stack) {
            throw new ModelException('Stack is not defined!');
        }

        $queryBuilder->setTable($stack);

        return $queryBuilder;
    }
}
