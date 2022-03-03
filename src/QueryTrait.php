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
 * A trait, fulfills all query building needs with short and descriptive methods.
 *
 * @package froq\database
 * @object  froq\database\QueryTrait
 * @author  Kerem Güneş
 * @since   4.0
 * @internal
 */
trait QueryTrait
{
    /**
     * @alias whereEqual()
     */
    public function equal(...$args)
    {
        return $this->whereEqual(...$args);
    }

    /**
     * @alias whereNotEqual()
     */
    public function notEqual(...$args)
    {
        return $this->whereNotEqual(...$args);
    }

    /**
     * @alias whereNull()
     */
    public function null(...$args)
    {
        return $this->whereNull(...$args);
    }

    /**
     * @alias whereNotNull()
     */
    public function notNull(...$args)
    {
        return $this->whereNotNull(...$args);
    }

    /**
     * @alias whereIs()
     * @since 5.0
     */
    public function is(...$args)
    {
        return $this->whereIs(...$args);
    }

    /**
     * @alias whereIsNot()
     * @since 5.0
     */
    public function isNot(...$args)
    {
        return $this->whereIsNot(...$args);
    }

    /**
     * @alias whereIn()
     */
    public function in(...$args)
    {
        return $this->whereIn(...$args);
    }

    /**
     * @alias whereNotIn()
     */
    public function notIn(...$args)
    {
        return $this->whereNotIn(...$args);
    }

    /**
     * @alias whereBetween()
     */
    public function between(...$args)
    {
        return $this->whereBetween(...$args);
    }

    /**
     * @alias whereNotBetween()
     */
    public function notBetween(...$args)
    {
        return $this->whereNotBetween(...$args);
    }

    /**
     * @alias whereLessThan()
     */
    public function lessThan(...$args)
    {
        return $this->whereLessThan(...$args);
    }

    /**
     * @alias whereLessThanEqual()
     */
    public function lessThanEqual(...$args)
    {
        return $this->whereLessThanEqual(...$args);
    }

    /**
     * @alias whereGreaterThan()
     */
    public function greaterThan(...$args)
    {
        return $this->whereGreaterThan(...$args);
    }

    /**
     * @alias whereGreaterThanEqual()
     */
    public function greaterThanEqual(...$args)
    {
        return $this->whereGreaterThanEqual(...$args);
    }

    /**
     * @alias whereLike()
     */
    public function like(...$args)
    {
        return $this->whereLike(...$args);
    }

    /**
     * @alias whereNotLike()
     */
    public function notLike(...$args)
    {
        return $this->whereNotLike(...$args);
    }

    /**
     * @alias whereLike()
     */
    public function likeStart(...$args)
    {
        $args[1] = ['', $args[1], '%'];

        return $this->whereLike(...$args);
    }

    /**
     * @alias whereNotLike()
     */
    public function notLikeStart(...$args)
    {
        $args[1] = ['', $args[1], '%'];

        return $this->whereNotLike(...$args);
    }

    /**
     * @alias whereLike()
     */
    public function likeEnd(...$args)
    {
        $args[1] = ['%', $args[1], ''];

        return $this->whereLike(...$args);
    }

    /**
     * @alias whereNotLike()
     */
    public function notLikeEnd(...$args)
    {
        $args[1] = ['%', $args[1], ''];

        return $this->whereNotLike(...$args);
    }

    /**
     * @alias whereLike()
     */
    public function likeBoth(...$args)
    {
        $args[1] = ['%', $args[1], '%'];

        return $this->whereLike(...$args);
    }

    /**
     * @alias whereNotLike()
     */
    public function notLikeBoth(...$args)
    {
        $args[1] = ['%', $args[1], '%'];

        return $this->whereNotLike(...$args);
    }

    /**
     * @alias whereExists()
     */
    public function exists(...$args)
    {
        return $this->whereExists(...$args);
    }

    /**
     * @alias whereNotExists()
     */
    public function notExists(...$args)
    {
        return $this->whereNotExists(...$args);
    }

    /**
     * @alias whereRandom()
     */
    public function random(...$args)
    {
        return $this->whereRandom(...$args);
    }

    /**
     * @alias groupBy()
     */
    public function group(...$args)
    {
        return $this->groupBy(...$args);
    }

    /**
     * @alias orderBy()
     */
    public function order(...$args)
    {
        return $this->orderBy(...$args);
    }

    /**
     * @alias orderBy()
     */
    public function sort(...$args)
    {
        return $this->orderBy(...$args);
    }

    /**
     * @alias orderByRandom()
     */
    public function sortRandom()
    {
        return $this->orderByRandom();
    }

    /**
     * @alias indexBy()
     */
    public function index(...$args)
    {
        return $this->indexBy(...$args);
    }

    /**
     * @alias whereEqual()
     */
    public function eq(...$args)
    {
        return $this->whereEqual(...$args);
    }

    /**
     * @alias whereNotEqual()
     */
    public function neq(...$args)
    {
        return $this->whereNotEqual(...$args);
    }

    /**
     * @alias whereLessThan()
     */
    public function lt(...$args)
    {
        return $this->whereLessThan(...$args);
    }

    /**
     * @alias whereLessThanEqual()
     */
    public function lte(...$args)
    {
        return $this->whereLessThanEqual(...$args);
    }

    /**
     * @alias whereGreaterThan()
     */
    public function gt(...$args)
    {
        return $this->whereGreaterThan(...$args);
    }

    /**
     * @alias whereGreaterThanEqual()
     */
    public function gte(...$args)
    {
        return $this->whereGreaterThanEqual(...$args);
    }

    /**
     * @alias selectMin()
     * @since 4.4
     */
    public function min(...$args)
    {
        return $this->selectMin(...$args);
    }

    /**
     * @alias selectMax()
     * @since 4.4
     */
    public function max(...$args)
    {
        return $this->selectMax(...$args);
    }

    /**
     * @alias selectAvg()
     * @since 4.4
     */
    public function avg(...$args)
    {
        return $this->selectAvg(...$args);
    }

    /**
     * @alias selectSum()
     * @since 4.4
     */
    public function sum(...$args)
    {
        return $this->selectSum(...$args);
    }

    /**
     * @alias aggregate()
     * @since 5.0
     */
    public function agg(...$args)
    {
        return $this->aggregate(...$args);
    }

    /**
     * @alias Database.escape()
     */
    public function esc(...$args)
    {
        return $this->db->escape(...$args);
    }

    /**
     * @alias Database.escapeName()
     */
    public function escName(...$args)
    {
        return $this->db->escapeName(...$args);
    }
}
