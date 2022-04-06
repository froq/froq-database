<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\query;

use froq\common\interface\Arrayable;

/**
 * A class, for using as a single param in query params.
 *
 * @package froq\database\query
 * @object  froq\database\query\QueryParam
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
        public string $field,
        public string $operator,
        public mixed  $value,
        public string $logic = 'AND'
    ) {}

    /**
     * @inheritDoc froq\common\interface\Arrayable
     */
    public function toArray(): array
    {
        return ['field' => $this->field, 'operator' => $this->operator,
                'value' => $this->value, 'logic'    => $this->logic];
    }
}
