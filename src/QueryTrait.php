<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
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
 * @author  Kerem Güneş
 * @since   4.0
 */
trait QueryTrait
{
    /**
     * @alias of whereEqual()
     */
    public function equal(...$args)
    {
        return $this->whereEqual(...$args);
    }

    /**
     * @alias of whereNotEqual()
     */
    public function notEqual(...$args)
    {
        return $this->whereNotEqual(...$args);
    }

    /**
     * @alias of whereNull()
     */
    public function null(...$args)
    {
        return $this->whereNull(...$args);
    }

    /**
     * @alias of whereNotNull()
     */
    public function notNull(...$args)
    {
        return $this->whereNotNull(...$args);
    }

    /**
     * @alias of whereIs()
     * @since 5.0
     */
    public function is(...$args)
    {
        return $this->whereIs(...$args);
    }

    /**
     * @alias of whereIsNot()
     * @since 5.0
     */
    public function isNot(...$args)
    {
        return $this->whereIsNot(...$args);
    }

    /**
     * @alias of whereIn()
     */
    public function in(...$args)
    {
        return $this->whereIn(...$args);
    }

    /**
     * @alias of whereNotIn()
     */
    public function notIn(...$args)
    {
        return $this->whereNotIn(...$args);
    }

    /**
     * @alias of whereBetween()
     */
    public function between(...$args)
    {
        return $this->whereBetween(...$args);
    }

    /**
     * @alias of whereNotBetween()
     */
    public function notBetween(...$args)
    {
        return $this->whereNotBetween(...$args);
    }

    /**
     * @alias of whereLessThan()
     */
    public function lessThan(...$args)
    {
        return $this->whereLessThan(...$args);
    }

    /**
     * @alias of whereLessThanEqual()
     */
    public function lessThanEqual(...$args)
    {
        return $this->whereLessThanEqual(...$args);
    }

    /**
     * @alias of whereGreaterThan()
     */
    public function greaterThan(...$args)
    {
        return $this->whereGreaterThan(...$args);
    }

    /**
     * @alias of whereGreaterThanEqual()
     */
    public function greaterThanEqual(...$args)
    {
        return $this->whereGreaterThanEqual(...$args);
    }

    /**
     * @alias of whereLike()
     */
    public function like(...$args)
    {
        return $this->whereLike(...$args);
    }

    /**
     * @alias of whereNotLike()
     */
    public function notLike(...$args)
    {
        return $this->whereNotLike(...$args);
    }

    /**
     * @alias of whereLike()
     */
    public function likeStart(...$args)
    {
        $args[1] = ['', $args[1], '%'];

        return $this->whereLike(...$args);
    }

    /**
     * @alias of whereNotLike()
     */
    public function notLikeStart(...$args)
    {
        $args[1] = ['', $args[1], '%'];

        return $this->whereNotLike(...$args);
    }

    /**
     * @alias of whereLike()
     */
    public function likeEnd(...$args)
    {
        $args[1] = ['%', $args[1], ''];

        return $this->whereLike(...$args);
    }

    /**
     * @alias of whereNotLike()
     */
    public function notLikeEnd(...$args)
    {
        $args[1] = ['%', $args[1], ''];

        return $this->whereNotLike(...$args);
    }

    /**
     * @alias of whereLike()
     */
    public function likeBoth(...$args)
    {
        $args[1] = ['%', $args[1], '%'];

        return $this->whereLike(...$args);
    }

    /**
     * @alias of whereNotLike()
     */
    public function notLikeBoth(...$args)
    {
        $args[1] = ['%', $args[1], '%'];

        return $this->whereNotLike(...$args);
    }

    /**
     * @alias of whereExists()
     */
    public function exists(...$args)
    {
        return $this->whereExists(...$args);
    }

    /**
     * @alias of whereNotExists()
     */
    public function notExists(...$args)
    {
        return $this->whereNotExists(...$args);
    }

    /**
     * @alias of whereRandom()
     */
    public function random(...$args)
    {
        return $this->whereRandom(...$args);
    }

    /**
     * @alias of groupBy()
     */
    public function group(...$args)
    {
        return $this->groupBy(...$args);
    }

    /**
     * @alias of orderBy()
     */
    public function order(...$args)
    {
        return $this->orderBy(...$args);
    }

    /**
     * @alias of orderBy()
     */
    public function sort(...$args)
    {
        return $this->orderBy(...$args);
    }

    /**
     * @alias of orderByRandom()
     */
    public function sortRandom()
    {
        return $this->orderByRandom();
    }

    /**
     * @alias of whereEqual()
     */
    public function eq(...$args)
    {
        return $this->whereEqual(...$args);
    }

    /**
     * @alias of whereNotEqual()
     */
    public function neq(...$args)
    {
        return $this->whereNotEqual(...$args);
    }

    /**
     * @alias of whereLessThan()
     */
    public function lt(...$args)
    {
        return $this->whereLessThan(...$args);
    }

    /**
     * @alias of whereLessThanEqual()
     */
    public function lte(...$args)
    {
        return $this->whereLessThanEqual(...$args);
    }

    /**
     * @alias of whereGreaterThan()
     */
    public function gt(...$args)
    {
        return $this->whereGreaterThan(...$args);
    }

    /**
     * @alias of whereGreaterThanEqual()
     */
    public function gte(...$args)
    {
        return $this->whereGreaterThanEqual(...$args);
    }

    /**
     * @alias of selectMin()
     * @since 4.4
     */
    public function min(...$args)
    {
        return $this->selectMin(...$args);
    }

    /**
     * @alias of selectMax()
     * @since 4.4
     */
    public function max(...$args)
    {
        return $this->selectMax(...$args);
    }

    /**
     * @alias of selectAvg()
     * @since 4.4
     */
    public function avg(...$args)
    {
        return $this->selectAvg(...$args);
    }

    /**
     * @alias of selectSum()
     * @since 4.4
     */
    public function sum(...$args)
    {
        return $this->selectSum(...$args);
    }

    /**
     * @alias of aggregate()
     * @since 5.0
     */
    public function agg(...$args)
    {
        return $this->aggregate(...$args);
    }

    /**
     * @alias of Database.escape()
     */
    public function esc(...$args)
    {
        return $this->db->escape(...$args);
    }

    /**
     * @alias of Database.escapeName()
     */
    public function escName(...$args)
    {
        return $this->db->escapeName(...$args);
    }
}
