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
    public function equal(...$args)
    {
        return $this->whereEqual(...$args);
    }

    /**
     * Not equal.
     * @alias of whereNotEqual()
     */
    public function notEqual(...$args)
    {
        return $this->whereNotEqual(...$args);
    }

    /**
     * Null.
     * @alias of whereNull()
     */
    public function null(...$args)
    {
        return $this->whereNull(...$args);
    }

    /**
     * Not null.
     * @alias of whereNotNull()
     */
    public function notNull(...$args)
    {
        return $this->whereNotNull(...$args);
    }

    /**
     * Is.
     * @alias of whereIs()
     * @since 5.0
     */
    public function is(...$args)
    {
        return $this->whereIs(...$args);
    }

    /**
     * Is not.
     * @alias of whereIsNot()
     * @since 5.0
     */
    public function isNot(...$args)
    {
        return $this->whereIsNot(...$args);
    }

    /**
     * In.
     * @alias of whereIn()
     */
    public function in(...$args)
    {
        return $this->whereIn(...$args);
    }

    /**
     * Not in.
     * @alias of whereNotIn()
     */
    public function notIn(...$args)
    {
        return $this->whereNotIn(...$args);
    }

    /**
     * Between.
     * @alias of whereBetween()
     */
    public function between(...$args)
    {
        return $this->whereBetween(...$args);
    }

    /**
     * Not between.
     * @alias of whereNotBetween()
     */
    public function notBetween(...$args)
    {
        return $this->whereNotBetween(...$args);
    }

    /**
     * Less than.
     * @alias of whereLessThan()
     */
    public function lessThan(...$args)
    {
        return $this->whereLessThan(...$args);
    }

    /**
     * Less than equal.
     * @alias of whereLessThanEqual()
     */
    public function lessThanEqual(...$args)
    {
        return $this->whereLessThanEqual(...$args);
    }

    /**
     * Greater than.
     * @alias of whereGreaterThan()
     */
    public function greaterThan(...$args)
    {
        return $this->whereGreaterThan(...$args);
    }

    /**
     * Greater than equal.
     * @alias of whereGreaterThanEqual()
     */
    public function greaterThanEqual(...$args)
    {
        return $this->whereGreaterThanEqual(...$args);
    }

    /**
     * Like.
     * @alias of whereLike()
     */
    public function like(...$args)
    {
        return $this->whereLike(...$args);
    }

    /**
     * Not like.
     * @alias of whereNotLike()
     */
    public function notLike(...$args)
    {
        return $this->whereNotLike(...$args);
    }

    /**
     * Like start.
     * @alias of whereLike()
     */
    public function likeStart(...$args)
    {
        $args[1] = ['', $args[1], '%'];

        return $this->whereLike(...$args);
    }

    /**
     * Not like start.
     * @alias of whereNotLike()
     */
    public function notLikeStart(...$args)
    {
        $args[1] = ['', $args[1], '%'];

        return $this->whereNotLike(...$args);
    }

    /**
     * Like end.
     * @alias of whereLike()
     */
    public function likeEnd(...$args)
    {
        $args[1] = ['%', $args[1], ''];

        return $this->whereLike(...$args);
    }

    /**
     * Not like end.
     * @alias of whereNotLike()
     */
    public function notLikeEnd(...$args)
    {
        $args[1] = ['%', $args[1], ''];

        return $this->whereNotLike(...$args);
    }

    /**
     * Like both.
     * @alias of whereLike()
     */
    public function likeBoth(...$args)
    {
        $args[1] = ['%', $args[1], '%'];

        return $this->whereLike(...$args);
    }

    /**
     * Not like both.
     * @alias of whereNotLike()
     */
    public function notLikeBoth(...$args)
    {
        $args[1] = ['%', $args[1], '%'];

        return $this->whereNotLike(...$args);
    }

    /**
     * Exists.
     * @alias of whereExists()
     */
    public function exists(...$args)
    {
        return $this->whereExists(...$args);
    }

    /**
     * Not exists.
     * @alias of whereNotExists()
     */
    public function notExists(...$args)
    {
        return $this->whereNotExists(...$args);
    }

    /**
     * Random.
     * @alias of whereRandom()
     */
    public function random(...$args)
    {
        return $this->whereRandom(...$args);
    }

    /**
     * Group.
     * @alias of groupBy()
     */
    public function group(...$args)
    {
        return $this->groupBy(...$args);
    }

    /**
     * Order.
     * @alias of orderBy()
     */
    public function order(...$args)
    {
        return $this->orderBy(...$args);
    }

    /**
     * Sort.
     * @alias of orderBy()
     */
    public function sort(...$args)
    {
        return $this->orderBy(...$args);
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
    public function eq(...$args)
    {
        return $this->whereEqual(...$args);
    }

    /**
     * Neq.
     * @alias of whereNotEqual()
     */
    public function neq(...$args)
    {
        return $this->whereNotEqual(...$args);
    }

    /**
     * Lt.
     * @alias of whereLessThan()
     */
    public function lt(...$args)
    {
        return $this->whereLessThan(...$args);
    }

    /**
     * Lte.
     * @alias of whereLessThanEqual()
     */
    public function lte(...$args)
    {
        return $this->whereLessThanEqual(...$args);
    }

    /**
     * Gt.
     * @alias of whereGreaterThan()
     */
    public function gt(...$args)
    {
        return $this->whereGreaterThan(...$args);
    }

    /**
     * Gte.
     * @alias of whereGreaterThanEqual()
     */
    public function gte(...$args)
    {
        return $this->whereGreaterThanEqual(...$args);
    }

    /**
     * Min.
     * @alias of selectMin()
     * @since 4.4
     */
    public function min(...$args)
    {
        return $this->selectMin(...$args);
    }

    /**
     * Max.
     * @alias of selectMax()
     * @since 4.4
     */
    public function max(...$args)
    {
        return $this->selectMax(...$args);
    }

    /**
     * Avg.
     * @alias of selectAvg()
     * @since 4.4
     */
    public function avg(...$args)
    {
        return $this->selectAvg(...$args);
    }

    /**
     * Sum.
     * @alias of selectSum()
     * @since 4.4
     */
    public function sum(...$args)
    {
        return $this->selectSum(...$args);
    }

    /**
     * Esc.
     * @alias of db.escape()
     */
    public function esc(...$args)
    {
        return $this->db->escape(...$args);
    }

    /**
     * Esc name.
     * @alias of db.escapeName()
     */
    public function escName(...$args)
    {
        return $this->db->escapeName(...$args);
    }
}
