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

use froq\database\DatabaseException;

/**
 * Query Trait.
 * @package froq\database
 * @object  froq\database\QueryTrait
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 */
trait QueryTrait
{
    /**
     * Equal.
     * @aliasOf whereEqual()
     */
    public function equal(...$arguments)
    {
        return $this->whereEqual(...$arguments);
    }

    /**
     * Not equal.
     * @aliasOf whereNotEqual()
     */
    public function notEqual(...$arguments)
    {
        return $this->whereNotEqual(...$arguments);
    }

    /**
     * Null.
     * @aliasOf whereNull()
     */
    public function null(...$arguments)
    {
        return $this->whereNull(...$arguments);
    }

    /**
     * Not nuull.
     * @aliasOf whereNotNull()
     */
    public function notNull(...$arguments)
    {
        return $this->whereNotNull(...$arguments);
    }

    /**
     * In.
     * @aliasOf whereIn()
     */
    public function in(...$arguments)
    {
        return $this->whereIn(...$arguments);
    }

    /**
     * Not in.
     * @aliasOf whereNotIn()
     */
    public function notIn(...$arguments)
    {
        return $this->whereNotIn(...$arguments);
    }

    /**
     * Between.
     * @aliasOf whereBetween()
     */
    public function between(...$arguments)
    {
        return $this->whereBetween(...$arguments);
    }

    /**
     * Not between.
     * @aliasOf whereNotBetween()
     */
    public function notBetween(...$arguments)
    {
        return $this->whereNotBetween(...$arguments);
    }

    /**
     * Less than.
     * @aliasOf whereLessThan()
     */
    public function lessThan(...$arguments)
    {
        return $this->whereLessThan(...$arguments);
    }

    /**
     * Less than equal.
     * @aliasOf whereLessThanEqual()
     */
    public function lessThanEqual(...$arguments)
    {
        return $this->whereLessThanEqual(...$arguments);
    }

    /**
     * Greater than.
     * @aliasOf whereGreaterThan()
     */
    public function greaterThan(...$arguments)
    {
        return $this->whereGreaterThan(...$arguments);
    }

    /**
     * Greater than equal.
     * @aliasOf whereGreaterThanEqual()
     */
    public function greaterThanEqual(...$arguments)
    {
        return $this->whereGreaterThanEqual(...$arguments);
    }

    /**
     * Like.
     * @aliasOf whereLike()
     */
    public function like(...$arguments)
    {
        return $this->whereLike(...$arguments);
    }

    /**
     * Not like.
     * @aliasOf whereNotLike()
     */
    public function notLike(...$arguments)
    {
        return $this->whereNotLike(...$arguments);
    }

    /**
     * Like start.
     * @aliasOf whereLike()
     */
    public function likeStart(...$arguments)
    {
        $arguments[1] = ['', $arguments[1], '%'];

        return $this->whereLike(...$arguments);
    }

    /**
     * Not like start.
     * @aliasOf whereNotLike()
     */
    public function notLikeStart(...$arguments)
    {
        $arguments[1] = ['', $arguments[1], '%'];

        return $this->whereNotLike(...$arguments);
    }

    /**
     * Like end.
     * @aliasOf whereLike()
     */
    public function likeEnd(...$arguments)
    {
        $arguments[1] = ['%', $arguments[1], ''];

        return $this->whereLike(...$arguments);
    }

    /**
     * Not like end.
     * @aliasOf whereNotLike()
     */
    public function notLikeEnd(...$arguments)
    {
        $arguments[1] = ['%', $arguments[1], ''];

        return $this->whereNotLike(...$arguments);
    }

    /**
     * Like both.
     * @aliasOf whereLike()
     */
    public function likeBoth(...$arguments)
    {
        $arguments[1] = ['%', $arguments[1], '%'];

        return $this->whereLike(...$arguments);
    }

    /**
     * Not like both.
     * @aliasOf whereNotLike()
     */
    public function notLikeBoth(...$arguments)
    {
        $arguments[1] = ['%', $arguments[1], '%'];

        return $this->whereNotLike(...$arguments);
    }

    /**
     * Exists.
     * @aliasOf whereExists()
     */
    public function exists(...$arguments)
    {
        return $this->whereExists(...$arguments);
    }

    /**
     * Not exists.
     * @aliasOf whereNotExists()
     */
    public function notExists(...$arguments)
    {
        return $this->whereNotExists(...$arguments);
    }

    /**
     * Random.
     * @aliasOf whereRandom()
     */
    public function random(...$arguments)
    {
        return $this->whereRandom(...$arguments);
    }

    /**
     * Group.
     * @aliasOf groupBy()
     */
    public function group(...$arguments)
    {
        return $this->groupBy(...$arguments);
    }

    /**
     * Order.
     * @aliasOf orderBy()
     */
    public function order(...$arguments)
    {
        return $this->orderBy(...$arguments);
    }

    /**
     * Sort.
     * @aliasOf orderBy()
     */
    public function sort(...$arguments)
    {
        return $this->orderBy(...$arguments);
    }

    /**
     * Sort random.
     * @aliasOf orderByRandom()
     */
    public function sortRandom()
    {
        return $this->orderByRandom();
    }

    /**
     * Eq.
     * @aliasOf whereEqual()
     */
    public function eq(...$arguments)
    {
        return $this->whereEqual(...$arguments);
    }

    /**
     * Neq.
     * @aliasOf whereNotEqual()
     */
    public function neq(...$arguments)
    {
        return $this->whereNotEqual(...$arguments);
    }

    /**
     * Lt.
     * @aliasOf whereLessThan()
     */
    public function lt(...$arguments)
    {
        return $this->whereLessThan(...$arguments);
    }

    /**
     * Lte.
     * @aliasOf whereLessThanEqual()
     */
    public function lte(...$arguments)
    {
        return $this->whereLessThanEqual(...$arguments);
    }

    /**
     * Gt.
     * @aliasOf whereGreaterThan()
     */
    public function gt(...$arguments)
    {
        return $this->whereGreaterThan(...$arguments);
    }

    /**
     * Gte.
     * @aliasOf whereGreaterThanEqual()
     */
    public function gte(...$arguments)
    {
        return $this->whereGreaterThanEqual(...$arguments);
    }

    /**
     * Min.
     * @aliasOf selectMin()
     * @since   4.4
     */
    public function min(...$arguments)
    {
        return $this->selectMin(...$arguments);
    }

    /**
     * Max.
     * @aliasOf selectMax()
     * @since   4.4
     */
    public function max(...$arguments)
    {
        return $this->selectMax(...$arguments);
    }

    /**
     * Avg.
     * @aliasOf selectAvg()
     * @since   4.4
     */
    public function avg(...$arguments)
    {
        return $this->selectAvg(...$arguments);
    }

    /**
     * Sum.
     * @aliasOf selectSum()
     * @since   4.4
     */
    public function sum(...$arguments)
    {
        return $this->selectSum(...$arguments);
    }

    /**
     * Esc.
     * @aliasOf db.escape()
     */
    public function esc(...$arguments)
    {
        return $this->db->escape(...$arguments);
    }

    /**
     * Esc name.
     * @aliasOf db.escapeName()
     */
    public function escName(...$arguments)
    {
        return $this->db->escapeName(...$arguments);
    }
}
