<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\query;

use froq\database\Query;
use froq\common\interface\Arrayable;

/**
 * A class, for collecting query params to use in query builder.
 *
 * @package froq\database\query
 * @object  froq\database\query\QueryParams
 * @author  Kerem Güneş
 * @since   6.0
 */
class QueryParams implements Arrayable
{
    /** @const array */
    public final const OPERATORS = [
        '=', '!=', '>', '<', '>=', '<=',
    ];

    /** @const array */
    public final const EQUAL_OPERATORS = [
        '=', '!='
    ];

    /** @var array */
    protected array $stack = [];

    /**
     * Constructor.
     */
    public function __construct()
    {}

    /**
     * Add param via QueryParam object.
     *
     * @param  froq\database\query\QueryParam $param
     * @return self
     */
    public function addParam(QueryParam $param): self
    {
        return $this->add($param->field, $param->operator, $param->value, $param->logic);
    }

    /**
     * Add param.
     *
     * @param  string $field
     * @param  string $operator
     * @param  mixed  $value
     * @param  string $logic
     * @return self
     */
    public function add(string $field, string $operator, mixed $value, string $logic = 'AND'): self
    {
        $this->stack[] = $this->pack($field, $operator, $value, $logic);

        return $this;
    }

    /**
     * Add in param.
     *
     * @param  string $field
     * @param  array  $value
     * @param  string $logic
     * @return self
     */
    public function addIn(string $field, array $value, string $logic = 'AND'): self
    {
        return $this->add($field, 'IN', $value, $logic);
    }

    /**
     * Add not in param.
     *
     * @param  string $field
     * @param  array  $value
     * @param  string $logic
     * @return self
     */
    public function addNotIn(string $field, array $value, string $logic = 'AND'): self
    {
        return $this->add($field, 'NOT-IN', $value, $logic);
    }

    /**
     * Add between param.
     *
     * @param  string $field
     * @param  array  $value
     * @param  string $logic
     * @return self
     */
    public function addBetween(string $field, array $value, string $logic = 'AND'): self
    {
        return $this->add($field, 'BETWEEN', $value, $logic);
    }

    /**
     * Add not between param.
     *
     * @param  string $field
     * @param  array  $value
     * @param  string $logic
     * @return self
     */
    public function addNotBetween(string $field, array $value, string $logic = 'AND'): self
    {
        return $this->add($field, 'NOT-BETWEEN', $value, $logic);
    }

    /**
     * Add like param.
     *
     * @param  string       $field
     * @param  string|array $value
     * @param  bool         $ilike
     * @param  string       $logic
     * @return self
     */
    public function addLike(string $field, string|array $value, bool $ilike = false, string $logic = 'AND'): self
    {
        return $this->add($field, $ilike ? 'ILIKE' : 'LIKE', $value, $logic);
    }

    /**
     * Add not like param.
     *
     * @param  string       $field
     * @param  string|array $value
     * @param  bool         $ilike
     * @param  string       $logic
     * @return self
     */
    public function addNotLike(string $field, string|array $value, bool $ilike = false, string $logic = 'AND'): self
    {
        return $this->add($field, $ilike ? 'NOT-ILIKE' : 'NOT-LIKE', $value, $logic);
    }

    /**
     * Add and logic operator.
     *
     * @return self
     */
    public function and(): self
    {
        return $this->logic('AND');
    }

    /**
     * Add or logic operator.
     *
     * @return self
     */
    public function or(): self
    {
        return $this->logic('OR');
    }

    /**
     * Reset.
     *
     * @return self
     */
    public function reset(): self
    {
        $this->stack = [];

        return $this;
    }

    /**
     * Apply to given query builder.
     *
     * @param  froq\database\Query $query
     * @return froq\database\Query
     */
    public function applyTo(Query $query): Query
    {
        foreach ($this->prepareFor($query) as [$where, $op]) {
            $query->where($where, null, $op);
        }

        return $query;
    }

    /**
     * Prepare for given query builder.
     *
     * @param  froq\database\Query $query
     * @return array
     * @throws ValueError
     */
    public function prepareFor(Query $query): array
    {
        $query = $query->clone(true);

        foreach ($this->stack as $param) {
            [$field, $operator, $value, $logic] = $this->unpack($param);

            switch ($operator = strtoupper($operator)) {
                case 'IN':
                    $query->whereIn($field, $value, $logic);
                    break;
                case 'NOT-IN':
                    $query->whereNotIn($field, $value, $logic);
                    break;
                case 'BETWEEN':
                    $query->whereBetween($field, $value, $logic);
                    break;
                case 'NOT-BETWEEN':
                    $query->whereNotBetween($field, $value, $logic);
                    break;
                case 'LIKE':
                case 'ILIKE':
                    $query->whereLike($field, $value, ($operator == 'ILIKE'), $logic);
                    break;
                case 'NOT-LIKE':
                case 'NOT-ILIKE':
                    $query->whereNotLike($field, $value, ($operator == 'NOT-ILIKE'), $logic);
                    break;
                default:
                    if (!in_array($operator, self::OPERATORS, true)) {
                        throw new \ValueError(format('Invalid operator %q', $operator));
                    }

                    if (!in_array($operator, self::EQUAL_OPERATORS, true)) {
                        $query->where(sprintf('%s %s ?', $field, $operator), [$value], $logic);
                    } else {
                        // In/not in stuff.
                        if (!is_array($value)) {
                            $query->where(sprintf('%s %s ?', $field, $operator), [$value], $logic);
                        } else {
                            $operator = ($operator == '=') ? 'IN' : 'NOT IN';
                            $query->where(sprintf('%s %s (?)', $field, $operator), [$value], $logic);
                        }
                    }
            }
        }

        return $query->pull('where') ?? [];
    }

    /**
     * @inheritDoc froq\common\interface\Arrayable
     */
    public function toArray(): array
    {
        return $this->stack;
    }

    /**
     * Add and/or logic operator.
     *
     * @throws Error
     */
    private function logic(string $logic): self
    {
        if (empty($this->stack)) {
            throw new \Error('No params yet, call add()');
        }

        $this->stack[count($this->stack) - 1]['logic'] = $logic;

        return $this;
    }

    /**
     * Pack param content.
     */
    private function pack(string $field, string $operator, mixed $value, string $logic): array
    {
        return ['field' => $field, 'operator' => $operator, 'value' => $value, 'logic' => $logic];
    }

    /**
     * Unpack param content.
     */
    private function unpack(array $param): array
    {
        ['field' => $field, 'operator' => $operator, 'value' => $value, 'logic' => $logic] = $param;

        return [$field, $operator, $value, $logic];
    }
}
