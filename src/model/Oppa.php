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

namespace froq\database\model;

use froq\database\DatabaseException;
use Oppa\Query\Result\ResultInterface;
use Oppa\Query\Builder as QueryBuilder;

/**
 * Oppa.
 * @package froq\database\model
 * @object  froq\database\model\Oppa
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   1.0
 */
class Oppa extends Model implements ModelInterface
{
    /**
     * Query.
     * @param  any ...$arguments (string $query, ?array $queryParams)
     * @return Oppa\Query\Result\ResultInterface|null
     */
    public function query(...$arguments) // don't define return type, so user can
    {
        $query       = $arguments[0] ?? '';
        $queryParams = $arguments[1] ?? null;

        try {
            return $this->vendor->getDatabase()->getLink()->getAgent()->query($query, $queryParams);
        } catch (\Exception $e) {
            $this->setFail($e);
            return null;
        }
    }

    /**
     * Find.
     * @param  any ...$arguments (?int|?string $pv)
     * @return any|null
     * @throws froq\database\DatabaseException
     */
    public function find(...$arguments) // don't define return type, so user can
    {
        $pv = $arguments[0] ?? null;
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
     * @param  any ...$arguments (?string $where, ?array $whereParams, int order, ?int limit)
     * @return array|null
     * @throws froq\database\DatabaseException
     */
    public function findAll(...$arguments) // don't define return type, so user can
    {
        $where       = $arguments[0] ?? null;
        $whereParams = $arguments[1] ?? null;
        $order       = $arguments[2] ?? 1;
        $limit       = $arguments[3] ?? null;

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
     * @throws froq\database\DatabaseException
     */
    public function save(): ?int
    {
        $agent = $this->vendor->getDatabase()->getLink()->getAgent();
        $batch = $this->useTransaction ? $agent->getBatch() : null;

        $batch && $batch->lock();

        $fail = null;
        $return = null;
        try {
            $query = $this->initQueryBuilder();

            $pv = $this->getStackPrimaryValue();
            if ($pv == null) { // insert
                $query = $query->insert($this->getData())->toString();
            } else {           // update
                $pn = $this->getStackPrimary();
                if ($pn == null) {
                    throw new DatabaseException(sprintf("Null \$stackPrimary, set it in '%s' class",
                        get_called_class()));
                }

                $data = $this->getData();

                // drop primary
                unset($data[$pn]);

                $query = $query->update($data)->whereEqual($pn, $pv)->toString();
            }

            if ($batch != null) {
                $result = $batch->doQuery($query)->getResult();
            } else {
                $result = $agent->query($query);
            }

            // free data
            $this->reset();

            // set return
            if ($pv != null) {
                $return = $result ? $result->getRowsAffected() : 0;
            } else {
                // set with new id
                $result && $this->setStackPrimaryValue($return = $result->getId());
            }
        } catch (DatabaseException $e) {
            $fail = $e;
        } catch (\Exception $e) {
            $fail = $e;

            // rollback
            if ($batch != null) {
                $batch->undo();
            }
        }

        $batch && $batch->unlock();

        // handle fail stuff
        if ($fail != null) {
            if ($fail instanceof DatabaseException) {
                throw $e;
            }
            $this->setFail($fail);
        }

        return $return;
    }

    /**
     * Remove.
     * @param  any ...$arguments (?int|?string $pv)
     * @return ?int
     * @throws froq\database\DatabaseException
     */
    public function remove(...$arguments): ?int
    {
        $pv = $arguments[0] ?? null;
        $pn = $this->getStackPrimary();
        if ($pn == null) {
            throw new DatabaseException(sprintf("Null \$stackPrimary, set it in '%s' class",
                get_called_class()));
        }

        $pv = $pv ?? $this->getStackPrimaryValue();
        if ($pv === null) {
            return null;
        }

        $agent = $this->vendor->getDatabase()->getLink()->getAgent();
        $batch = $this->useTransaction ? $agent->getBatch() : null;

        $batch && $batch->lock();

        $fail = null;
        $return = null;
        try {
            $query = $this->initQueryBuilder();
            $query = $query->delete()->whereEqual($pn, $pv)->toString();

            if ($batch != null) {
                $result = $batch->doQuery($query)->getResult();
            } else {
                $result = $agent->query($query);
            }

            // set return
            $return = $result ? $result->getRowsAffected() : 0;
        } catch (DatabaseException $e) {
            $fail = $e;
        } catch (\Exception $e) {
            $fail = $e;

            // rollback
            if ($batch != null) {
                $batch->undo();
            }
        }

        $batch && $batch->unlock();

        // handle fail stuff
        if ($fail != null) {
            if ($fail instanceof DatabaseException) {
                throw $e;
            }
            $this->setFail($fail);
        }

        return $return;
    }

    /**
     * Count.
     * @param  any ...$arguments (?string $where, ?array $whereParams)
     * @return ?int
     */
    public function count(...$arguments): ?int
    {
        $where       = $arguments[0] ?? null;
        $whereParams = $arguments[1] ?? null;

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
     * @throws froq\database\DatabaseException
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
