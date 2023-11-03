<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\query;

use froq\common\interface\Arrayable;

/**
 * A class, for using as a single param in query params.
 *
 * @package froq\database\query
 * @class   froq\database\query\QueryParam
 * @author  Kerem Güneş
 * @since   6.0
 */
class QueryParam implements Arrayable
{
    /**
     * Constructor.
     *
     * @param string $field
     * @param string $operator
     * @param mixed  $value
     * @param string $logic
     */
    public function __construct(
        public string $field    = '',
        public string $operator = '',
        public mixed  $value    = '',
        public string $logic    = 'AND'
    ) {}

    /**
     * Set field.
     *
     * @param  string $field
     * @return self
     */
    public function setField(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    /**
     * Get field.
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Set operator.
     *
     * @param  string $operator
     * @return self
     */
    public function setOperator(string $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    /**
     * Get operator.
     *
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Set value.
     *
     * @param  mixed $value
     * @return self
     */
    public function setValue(mixed $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Set logic.
     *
     * @param  string $logic
     * @return self
     */
    public function setLogic(string $logic): self
    {
        $this->logic = $logic;

        return $this;
    }

    /**
     * Get logic.
     *
     * @return string
     */
    public function getLogic(): string
    {
        return $this->logic;
    }

    /**
     * @inheritDoc froq\common\interface\Arrayable
     */
    public function toArray(): array
    {
        return ['field' => $this->field, 'operator' => $this->operator,
                'value' => $this->value, 'logic'    => $this->logic];
    }
}
