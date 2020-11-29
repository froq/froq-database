<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database;

/**
 * Query Trait.
 *
 * Represents a query builder trait entity which mostly fulfills all building needs with short and
 * descriptive methods.
 *
 * @package froq\database
 * @object  froq\database\QueryTrait
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 */
trait QueryTrait
{
    /**
     * Equal.
     * @alias of whereEqual()
     */
    public function equal(...$arguments)
    {
        return $this->whereEqual(...$arguments);
    }

    /**
     * Not equal.
     * @alias of whereNotEqual()
     */
    public function notEqual(...$arguments)
    {
        return $this->whereNotEqual(...$arguments);
    }

    /**
     * Null.
     * @alias of whereNull()
     */
    public function null(...$arguments)
    {
        return $this->whereNull(...$arguments);
    }

    /**
     * Not null.
     * @alias of whereNotNull()
     */
    public function notNull(...$arguments)
    {
        return $this->whereNotNull(...$arguments);
    }

    /**
     * Is.
     * @alias of whereIs()
     * @since 5.0
     */
    public function is(...$arguments)
    {
        return $this->whereIs(...$arguments);
    }

    /**
     * Is not.
     * @alias of whereIsNot()
     * @since 5.0
     */
    public function isNot(...$arguments)
    {
        return $this->whereIsNot(...$arguments);
    }

    /**
     * In.
     * @alias of whereIn()
     */
    public function in(...$arguments)
    {
        return $this->whereIn(...$arguments);
    }

    /**
     * Not in.
     * @alias of whereNotIn()
     */
    public function notIn(...$arguments)
    {
        return $this->whereNotIn(...$arguments);
    }

    /**
     * Between.
     * @alias of whereBetween()
     */
    public function between(...$arguments)
    {
        return $this->whereBetween(...$arguments);
    }

    /**
     * Not between.
     * @alias of whereNotBetween()
     */
    public function notBetween(...$arguments)
    {
        return $this->whereNotBetween(...$arguments);
    }

    /**
     * Less than.
     * @alias of whereLessThan()
     */
    public function lessThan(...$arguments)
    {
        return $this->whereLessThan(...$arguments);
    }

    /**
     * Less than equal.
     * @alias of whereLessThanEqual()
     */
    public function lessThanEqual(...$arguments)
    {
        return $this->whereLessThanEqual(...$arguments);
    }

    /**
     * Greater than.
     * @alias of whereGreaterThan()
     */
    public function greaterThan(...$arguments)
    {
        return $this->whereGreaterThan(...$arguments);
    }

    /**
     * Greater than equal.
     * @alias of whereGreaterThanEqual()
     */
    public function greaterThanEqual(...$arguments)
    {
        return $this->whereGreaterThanEqual(...$arguments);
    }

    /**
     * Like.
     * @alias of whereLike()
     */
    public function like(...$arguments)
    {
        return $this->whereLike(...$arguments);
    }

    /**
     * Not like.
     * @alias of whereNotLike()
     */
    public function notLike(...$arguments)
    {
        return $this->whereNotLike(...$arguments);
    }

    /**
     * Like start.
     * @alias of whereLike()
     */
    public function likeStart(...$arguments)
    {
        $arguments[1] = ['', $arguments[1], '%'];

        return $this->whereLike(...$arguments);
    }

    /**
     * Not like start.
     * @alias of whereNotLike()
     */
    public function notLikeStart(...$arguments)
    {
        $arguments[1] = ['', $arguments[1], '%'];

        return $this->whereNotLike(...$arguments);
    }

    /**
     * Like end.
     * @alias of whereLike()
     */
    public function likeEnd(...$arguments)
    {
        $arguments[1] = ['%', $arguments[1], ''];

        return $this->whereLike(...$arguments);
    }

    /**
     * Not like end.
     * @alias of whereNotLike()
     */
    public function notLikeEnd(...$arguments)
    {
        $arguments[1] = ['%', $arguments[1], ''];

        return $this->whereNotLike(...$arguments);
    }

    /**
     * Like both.
     * @alias of whereLike()
     */
    public function likeBoth(...$arguments)
    {
        $arguments[1] = ['%', $arguments[1], '%'];

        return $this->whereLike(...$arguments);
    }

    /**
     * Not like both.
     * @alias of whereNotLike()
     */
    public function notLikeBoth(...$arguments)
    {
        $arguments[1] = ['%', $arguments[1], '%'];

        return $this->whereNotLike(...$arguments);
    }

    /**
     * Exists.
     * @alias of whereExists()
     */
    public function exists(...$arguments)
    {
        return $this->whereExists(...$arguments);
    }

    /**
     * Not exists.
     * @alias of whereNotExists()
     */
    public function notExists(...$arguments)
    {
        return $this->whereNotExists(...$arguments);
    }

    /**
     * Random.
     * @alias of whereRandom()
     */
    public function random(...$arguments)
    {
        return $this->whereRandom(...$arguments);
    }

    /**
     * Group.
     * @alias of groupBy()
     */
    public function group(...$arguments)
    {
        return $this->groupBy(...$arguments);
    }

    /**
     * Order.
     * @alias of orderBy()
     */
    public function order(...$arguments)
    {
        return $this->orderBy(...$arguments);
    }

    /**
     * Sort.
     * @alias of orderBy()
     */
    public function sort(...$arguments)
    {
        return $this->orderBy(...$arguments);
    }

    /**
     * Sort random.
     * @alias of orderByRandom()
     */
    public function sortRandom()
    {
        return $this->orderByRandom();
    }

    /**
     * Eq.
     * @alias of whereEqual()
     */
    public function eq(...$arguments)
    {
        return $this->whereEqual(...$arguments);
    }

    /**
     * Neq.
     * @alias of whereNotEqual()
     */
    public function neq(...$arguments)
    {
        return $this->whereNotEqual(...$arguments);
    }

    /**
     * Lt.
     * @alias of whereLessThan()
     */
    public function lt(...$arguments)
    {
        return $this->whereLessThan(...$arguments);
    }

    /**
     * Lte.
     * @alias of whereLessThanEqual()
     */
    public function lte(...$arguments)
    {
        return $this->whereLessThanEqual(...$arguments);
    }

    /**
     * Gt.
     * @alias of whereGreaterThan()
     */
    public function gt(...$arguments)
    {
        return $this->whereGreaterThan(...$arguments);
    }

    /**
     * Gte.
     * @alias of whereGreaterThanEqual()
     */
    public function gte(...$arguments)
    {
        return $this->whereGreaterThanEqual(...$arguments);
    }

    /**
     * Min.
     * @alias of selectMin()
     * @since 4.4
     */
    public function min(...$arguments)
    {
        return $this->selectMin(...$arguments);
    }

    /**
     * Max.
     * @alias of selectMax()
     * @since 4.4
     */
    public function max(...$arguments)
    {
        return $this->selectMax(...$arguments);
    }

    /**
     * Avg.
     * @alias of selectAvg()
     * @since 4.4
     */
    public function avg(...$arguments)
    {
        return $this->selectAvg(...$arguments);
    }

    /**
     * Sum.
     * @alias of selectSum()
     * @since 4.4
     */
    public function sum(...$arguments)
    {
        return $this->selectSum(...$arguments);
    }

    /**
     * Esc.
     * @alias of db.escape()
     */
    public function esc(...$arguments)
    {
        return $this->db->escape(...$arguments);
    }

    /**
     * Esc name.
     * @alias of db.escapeName()
     */
    public function escName(...$arguments)
    {
        return $this->db->escapeName(...$arguments);
    }
}
