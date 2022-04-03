<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\result\{Ids, Rows, Row};
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
    /** @const array */
    public const FETCH = [
        'array'  => PDO::FETCH_ASSOC,
        'object' => PDO::FETCH_OBJ,
        'class'  => PDO::FETCH_CLASS,
    ];

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

        // Normally an exception must be thrown until here.
        if ($pdo->errorCode() != '00000' || $pdoStatement->errorCode() != '00000') {
            return;
        }

        $options = self::prepareOptions($options);

        // Set fetch option.
        if ($options['fetch']) {
            $fetch =@ $options['fetch'][0];
            if ($fetch) {
                $fetchType =@ self::FETCH[$fetch];
                $fetchType || throw new ResultException(
                    'Invalid fetch type `%s` [valids: %a]',
                    [$fetch, array_keys(self::FETCH)]
                );

                if ($fetchType == PDO::FETCH_CLASS) {
                    $fetchClass =@ $options['fetch'][1];
                    $fetchClass || throw new ResultException(
                        'No fetch class given, it is required when fetch type '.
                        'is `class` [tip: give it as second item of `fetch` option]'
                    );
                }
            }
        }

        // Assign count (affected rows etc).
        $this->count = $pdoStatement->rowCount();

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

        // Select & other fetchable queries (eg: insert/update/delete with returning clause).
        if ($this->count && $pdoStatement->columnCount()) {
            // Use present type that was set above or get default.
            $fetchType ??= $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);

            // @tome: Data is populated before the constructor is called.
            // To populate data after the constructor use PDO::FETCH_PROPS_LATE.
            $rows = ($fetchType == PDO::FETCH_CLASS)
                  ? $pdoStatement->fetchAll($fetchType|PDO::FETCH_PROPS_LATE, $fetchClass)
                  : $pdoStatement->fetchAll($fetchType);

            $this->rows->add(...$rows);
            unset($rows);
        }
    }

    /**
     * Get a copy of ids property.
     *
     * @return froq\database\result\Ids
     */
    public function getIds(): Ids
    {
        return (clone $this->ids);
    }

    /**
     * Get a copy of rows property.
     *
     * @param  bool $init
     * @return froq\database\result\Rows
     */
    public function getRows(bool $init = false): Rows
    {
        $rows = (clone $this->rows);
        if ($init) foreach ($rows as $i => $row) {
            $rows[$i] = $this->toRow($row);
        }
        return $rows;
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
            // When class consumes row fields as property.
            foreach ($this->toArray() as $row) {
                $class = new $class(...$ctorArgs);
                foreach ($row as $name => $value) {
                    $class->$name = $value;
                }
                $ret[] = $class;
            }
        } else {
            // When class consumes row as parameter.
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
     * @param  int  $index
     * @param  bool $init
     * @return array|object|null
     */
    public function row(int $index, bool $init = false): array|object|null
    {
        $row = $this->rows->item($index);
        return ($init && $row) ? $this->toRow($row) : $row;
    }

    /**
     * Get one row or all rows.
     *
     * @param  int|null $index
     * @param  bool     $init
     * @return array<array|object>|array|object|null
     */
    public function rows(int $index = null, bool $init = false): array|object|null
    {
        if ($index !== null) {
            $row = $this->rows->item($index);
            return ($init && $row) ? $this->toRow($row) : $row;
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
     * Get only columns by given index/field(s).
     *
     * @param  int          $index
     * @param  string|array $field
     * @return mixed
     */
    public function cols(int $index, string|array $field): mixed
    {
        $row = $this->rows($index);

        if ($row && $field != '*') {
            $orow = new Row((array) $row);
            // Single field.
            if (is_string($field)) {
                if ($orow->has($field)) {
                    return $orow->get($field);
                }
            } elseif (is_array($field)) {
                $vals = $orow->select($field, combine: true);
                return is_array($row) ? $vals : (object) $vals;
            }
        }

        return $row;
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
     * Free all properties.
     *
     * @return void
     * @since  6.0
     */
    public function free(): void
    {
        // This will set all properties as "uninitialized".
        unset($this->count, $this->ids, $this->rows);
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
        return $this->rows->offsetExists($index);
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public function offsetGet(mixed $index): mixed
    {
        return $this->rows->offsetGet($index);
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
     * Create a `Row` instance.
     */
    private function toRow(array|object $data): Row
    {
        return new Row((array) $data);
    }

    /**
     * Prepare options with defaults for constructor.
     */
    private static function prepareOptions(array|null $options): array
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
