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

namespace Froq\Database\Model;

use Froq\Database\DatabaseException;
use Oppa\Query\Result\ResultInterface;
use Oppa\Query\Builder as QueryBuilder;

/**
 * @package    Froq
 * @subpackage Froq\Database
 * @object     Froq\Database\Model\Oppa
 * @author     Kerem Güneş <k-gun@mail.com>
 */
class Oppa extends Model implements ModelInterface
{
    /**
     * Query.
     * @param  string     $query
     * @param  array|null $queryParams
     * @return ?Oppa\Query\Result\ResultInterface
     */
    public function query(string $query = '', array $queryParams = null): ?ResultInterface
    {
        try {
            return $this->vendor->getDatabase()->getLink()->getAgent()->query($query, $queryParams);
        } catch (\Exception $e) {
            $this->setFail($e);
            return null;
        }
    }

    /**
     * Find.
     * @param  int|string $pv
     * @return any
     * @throws Froq\Database\DatabaseException
     */
    public function find($pv = null)
    {
        $pn = $this->getStackPrimary();
        if ($pn == null) {
            throw new DatabaseException(sprintf("Null \$stackPrimary, set it in '%s' class",
                get_called_class()));
        }

        $pv = $pv ?? $this->getStackPrimaryValue();
        if ($pv === null) {
            return null;
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
     * @throws Froq\Database\DatabaseException
     */
    public function findAll(string $where = null, array $whereParams = null, int $limit = null,
        int $order = 1): ?array
    {
        $pn = $this->getStackPrimary();
        if ($pn == null) {
            throw new DatabaseException(sprintf("Null \$stackPrimary, set it in '%s' class",
                get_called_class()));
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
     * @throws Froq\Database\DatabaseException
     */
    public function save(): ?int
    {
        $batch = null;
        $agent = $this->vendor->getDatabase()->getLink()->getAgent();
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
            } else {           // update
                $pn = $this->getStackPrimary();
                if ($pn == null) {
                    throw new DatabaseException(sprintf("Null \$stackPrimary, set it in '%s' class",
                        get_called_class()));
                }

                // drop primary
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
        } catch (DatabaseException $e) {
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
     * @param  int|string $pv
     * @return ?int
     * @throws Froq\Database\DatabaseException
     */
    public function remove($pv = null): ?int
    {
        $pn = $this->getStackPrimary();
        if ($pn == null) {
            throw new DatabaseException(sprintf("Null \$stackPrimary, set it in '%s' class",
                get_called_class()));
        }

        $pv = $pv ?? $this->getStackPrimaryValue();
        if ($pv === null) {
            return null;
        }

        $batch = null;
        $agent = $this->vendor->getDatabase()->getLink()->getAgent();
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
     * @throws Froq\Database\DatabaseException
     */
    public final function initQueryBuilder(string $stack = null): QueryBuilder
    {
        $stack = $stack ?? $this->getStack(); // use self stack if $stack is null
        if ($stack == null) {
            throw new DatabaseException(sprintf("Null \$stack, set it in '%s' class",
                get_called_class()));
        }

        return new QueryBuilder($this->vendor->getDatabase()->getLink(), $stack);
    }
}
