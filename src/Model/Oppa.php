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
 * @subpackage Froq\Database
 * @object     Froq\Database\Model\Oppa
 * @author     Kerem Güneş <k-gun@mail.com>
 */
class Oppa extends Model
{
    /**
     * @inheritDoc Froq\Database\Model\Model
     */
    public final function __construct()
    {
        parent::__construct();
    }

    /**
     * Query.
     * @param  string     $query
     * @param  array|null $queryParams
     * @return any
     */
    public function query(string $query = '', array $queryParams = null)
    {
        if ($query == '') {
            throw new ModelException('Query is empty!');
        }

        try {
            return $this->vendor->getLink()->getAgent()->query($query, $queryParams);
        } catch (\Exception $e) {
            $this->setFail($e);
        }
    }

    /**
     * Find.
     * @param  int|string $id
     * @return any
     */
    public function find($pv = null)
    {
        $pn = $this->getStackPrimary();
        if ($pn == null) {
            throw new ModelException(sprintf('None $stackPrimary, set it in %s first!', get_called_class()));
        }

        $pv = $pv ?? $this->getStackPrimaryValue();
        if ($pv === null) {
            return;
        }

        $query = $this->initQueryBuilder();
        try {
            return $query->select('*')->whereEqual($pn, $pv)->limit(1)->get();
        } catch (\Exception $e) {
            $this->setFail($e);
        }
    }

    /**
     * Find all.
     * @param  string|null $where
     * @param  array|null  $whereParams
     * @param  int|null    $limit
     * @param  int         $order
     * @return ?array
     */
    public function findAll(string $where = null, array $whereParams = null, int $limit = null,
        int $order = 1): ?array
    {
        $pn = $this->getStackPrimary();
        if ($pn == null) {
            throw new ModelException(sprintf('Null $stackPrimary, set it in %s first!', get_called_class()));
        }

        $query = $this->initQueryBuilder();
        try {
            $query->select('*');
            if ($where != null) {
                $query->where($where, $whereParams);
            }

            if ($order == 1) {
                $query->orderBy($pn, QueryBuilder::OP_ASC);
            } elseif ($order == -1) {
                $query->orderBy($pn, QueryBuilder::OP_DESC);
            }

            if ($limit === null) { // null => paginate
                [$start, $stop] = $this->pager->run($query->count());
                if ($start || $stop) {
                    $query->limit($start, $stop);
                }
            } elseif ($limit != -1) { // -1 => no limit
                $query->limit($limit);
            }

            return $query->getAll();
        } catch (\Exception $e) {
            $this->setFail($e);

            return null;
        }
    }

    /**
     * Save
     * @return ?int
     */
    public function save(): ?int
    {
        $batch = null;
        $agent = $this->vendor->getLink()->getAgent();
        if ($this->usesTransaction()) {
            $batch = $agent->getBatch();
            $batch->lock();
        }


        $return = null;
        $query = $this->initQueryBuilder();
        try {
            $data = $this->getData();

            $pv = $this->getStackPrimaryValue();
            if ($pv == null) { // insert
                $query = $query->insert($data)->toString();
            } else {    // update
                $pn = $this->getStackPrimary();
                if ($pn == null) {
                    throw new ModelException(sprintf('Null $stackPrimary, set it in %s first!',
                        get_called_class()));
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
        } catch (ModelException $e) {
            throw $e;
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
     * @return ?int
     */
    public function remove(): ?int
    {
        $pn = $this->getStackPrimary();
        if ($pn == null) {
            throw new ModelException(sprintf('Null $stackPrimary, set it in %s first!', get_called_class()));
        }

        $pv = $this->getStackPrimaryValue();
        if ($pv == null) {
            return null;
        }

        $batch = null;
        $agent = $this->vendor->getLink()->getAgent();
        if ($this->usesTransaction()) {
            $batch = $agent->getBatch();
            $batch->lock();
        }

        $return = null;
        $query = $this->initQueryBuilder();
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
     * @return ?int
     */
    public function count(string $where = null, array $whereParams = null): ?int
    {
        $query = $this->initQueryBuilder();
        try {
            $query->select('1');

            if ($where != null) {
                $query->where($where, $whereParams);
            }

            return $query->count();
        } catch (\Exception $e) {
            $this->setFail($e);

            return null;
        }
    }

    /**
     * Init query builder.
     * @param  string|null $stack
     * @return Oppa\Query\Builder
     */
    public final function initQueryBuilder(string $stack = null): QueryBuilder
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setLink($this->vendor->getLink());

        // use self stack
        $stack =  $stack ?: $this->getStack();
        if ($stack == null) {
            throw new ModelException(sprintf('Null $stack, set it in %s first!', get_called_class()));
        }

        $queryBuilder->setTable($stack);

        return $queryBuilder;
    }
}
