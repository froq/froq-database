<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\result\{Ids, Rows};
use froq\common\interface\Arrayable;
use PDO, PDOStatement, PDOException;

/**
 * A class, for query result stuff such as count, ids & rows.
 *
 * @package froq\database
 * @object  froq\database\Result
 * @author  Kerem Güneş
 * @since   4.0
 */
final class Result implements Arrayable, \Countable, \IteratorAggregate, \ArrayAccess
{
    /** @const array<string> */
    public const FETCH_TYPES = ['array', 'object', 'class'];

    /** @var int */
    private int $count = 0;

    /** @var froq\database\result\Ids<int> */
    private Ids $ids;

    /** @var froq\database\result\Rows<array|object> */
    private Rows $rows;

    /**
     * Constructor.
     * @param  PDO          $pdo
     * @param  PDOStatement $pdoStatement
     * @param  array|null   $options
     * @throws froq\database\ResultException
     */
    public function __construct(PDO $pdo, PDOStatement $pdoStatement, array $options = null)
    {
        $this->ids  = new Ids();
        $this->rows = new Rows();

        if ($pdo->errorCode() == '00000' && $pdoStatement->errorCode() == '00000') {
            $options = $this->prepareOptions($options);

            // Check fetch option.
            if ($options['fetch']) {
                switch ($fetchType = $options['fetch'][0]) {
                    case  'array': $fetchType = PDO::FETCH_ASSOC; break;
                    case 'object': $fetchType = PDO::FETCH_OBJ;   break;
                    case  'class':
                        if (empty($options['fetch'][1])) {
                            throw new ResultException(
                                'No fetch class given, it is required when fetch type '.
                                'is `class` [tip: give it as second item of `fetch` option]'
                            );
                        }

                        $fetchType  = PDO::FETCH_CLASS;
                        $fetchClass = $options['fetch'][1];
                        break;
                    default:
                        if ($fetchType && !in_array($fetchType, self::FETCH_TYPES, true)) {
                            throw new ResultException(
                                'Invalid fetch type `%s` [valids: %a]',
                                [$fetchType, self::FETCH_TYPES]
                            );
                        }

                        // For default below.
                        $fetchType = null;
                }
            }

            // Assign count (affected rows etc).
            $this->count = $pdoStatement->rowCount();

            // Select & other fetchable queries (eg: insert/update/delete with returning clause).
            if ($this->count && $pdoStatement->columnCount()) {
                // Use present type that was set above or get default.
                $fetchType ??= $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);

                $rows = (
                    ($fetchType == PDO::FETCH_CLASS)
                        ? $pdoStatement->fetchAll($fetchType, $fetchClass)
                        : $pdoStatement->fetchAll($fetchType)
                ) ?: [];

                $this->rows->add(...$rows);
                unset($rows);
            }

            // Note: Sequence option to prevent transaction errors that comes from lastInsertId()
            // calls but while commit() returning true when sequence field not exists. Default is
            // true for "INSERT" queries if no "sequence" option given.
            $sequence = $options['sequence'] && preg_match('~^\s*INSERT~i', $pdoStatement->queryString);

            // Insert queries.
            if ($this->count && $sequence) {
                $id = null;

                // Prevent "SQLSTATE[55000]: Object not in prerequisite state: 7 ..." error that mostly
                // occurs when a user-provided ID given to insert data. Sequence option for this but cannot
                // prevent transaction commits when no sequence field exists.
                try {
                    $id = (int) $pdo->lastInsertId();
                } catch (PDOException) {}

                if ($id) {
                    $ids = [$id];

                    // Handle multiple inserts.
                    if ($this->count > 1) {
                        // MySQL awesomeness, last id is first id..
                        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
                            $start = $id;
                            $end   = $id + $this->count - 1;
                        } else {
                            $start = $id - $this->count + 1;
                            $end   = $id;
                        }

                        $ids = range($start, $end);
                    }

                    $this->ids->add(...$ids);
                    unset($ids);
                }
            }
        }
    }

    /**
     * @inheritDoc froq\common\interface\Arrayable
     */
    public function toArray(): array
    {
        return $this->rows->toArray();
    }

    /**
     * Get rows as given class instance.
     *
     * @param  string $class
     * @param  bool   $ctor
     * @param  array  $ctorArgs
     * @return array<object>
     */
    public function toClass(string $class, bool $ctor = false, array $ctorArgs = []): array
    {
        $ret = [];

        if (!$ctor) {
            foreach ($this->toArray() as $row) {
                $class = new $class(...$ctorArgs);
                foreach ($row as $name => $value) {
                    $class->{$name} = $value;
                }
                $ret[] = $class;
            }
        } else {
            foreach ($this->toArray() as $row) {
                $ret[] = new $class($row, ...$ctorArgs);
            }
        }

        return $ret;
    }

    /**
     * Get last insert id.
     *
     * @return int|null
     */
    public function id(): int|null
    {
        return $this->ids->last();
    }

    /**
     * Get one or all insert ids.
     *
     * @param  int|null $index
     * @return int|array<int>|null
     */
    public function ids(int $index = null): int|array|null
    {
        if ($index !== null) {
            return $this->ids->item($index);
        }
        return $this->ids->items();
    }

    /**
     * Get one row.
     *
     * @param  int $index
     * @return array|object|null
     */
    public function row(int $index): array|object|null
    {
        return $this->rows->item($index);
    }

    /**
     * Get one row or all rows.
     *
     * @param  int|null $index
     * @return array<array|object>|array|object|null
     */
    public function rows(int $index = null): array|object|null
    {
        if ($index !== null) {
            return $this->rows->item($index);
        }
        return $this->rows->items();
    }

    /**
     * Get first row.
     *
     * @return array|object|null
     */
    public function first(): array|object|null
    {
        return $this->rows->first();
    }

    /**
     * Get last row.
     *
     * @return array|object|null
     */
    public function last(): array|object|null
    {
        return $this->rows->last();
    }

    /**
     * Sort rows.
     *
     * @param  callable|null $func
     * @param  int           $flags
     * @return self
     * @since  6.0
     */
    public function sort(callable $func = null, int $flags = 0): self
    {
        $this->rows->sort($func, $flags);

        return $this;
    }

    /**
     * Each for rows.
     *
     * @param  callable $func
     * @return self
     * @since  5.4
     */
    public function each(callable $func): self
    {
        $this->rows->each($func);

        return $this;
    }

    /**
     * Filter rows.
     *
     * @param  callable $func
     * @return self
     * @since  5.0
     */
    public function filter(callable $func): self
    {
        $this->rows->filter($func);

        return $this;
    }

    /**
     * Map rows.
     *
     * @param  callable $func
     * @return self
     * @since  5.0
     */
    public function map(callable $func): self
    {
        $this->rows->map($func);

        return $this;
    }

    /**
     * Reduce rows.
     *
     * @param  mixed    $carry
     * @param  callable $func
     * @return mixed
     * @since  5.0
     */
    public function reduce(mixed $carry, callable $func): mixed
    {
        return $this->rows->reduce($carry, $func);
    }

    /**
     * Reverse rows.
     *
     * @return self
     * @since  6.0
     */
    public function reverse(): self
    {
        $this->rows->reverse();

        return $this;
    }

    /**
     * @inheritDoc Countable
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @inheritDoc IteratorAggregate
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public function offsetExists(mixed $index): bool
    {
        return $this->row($index) !== null;
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public function offsetGet(mixed $index): array|object|null
    {
        return $this->row($index);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     ReadonlyError
     */
    public function offsetSet(mixed $index, mixed $_): never
    {
        throw new \ReadonlyError($this);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     ReadonlyError
     */
    public function offsetUnset(mixed $index): never
    {
        throw new \ReadonlyError($this);
    }

    /**
     * Prepare options with defaults for constructor.
     */
    private function prepareOptions(array|null $options): array
    {
        static $optionsDefault = [
            'fetch' => null, 'sequence' => true,
        ];

        $options = [...$optionsDefault, ...$options ?? []];

        $options['fetch']    = (array) $options['fetch'];
        $options['sequence'] = (bool) ($options['sequence'] ?? true);

        return $options;
    }
}
