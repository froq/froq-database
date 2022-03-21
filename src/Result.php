<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\common\interface\{Arrayable, Listable, Objectable};
use froq\collection\Collection;
use PDO, PDOStatement, PDOException;

/**
 * Result.
 *
 * @package froq\database
 * @object  froq\database\Result
 * @author  Kerem Güneş
 * @since   4.0
 */
final class Result implements Arrayable, Listable, Objectable, \Countable, \IteratorAggregate, \ArrayAccess
{
    /** @const array<string> */
    public const FETCH_TYPES = ['array', 'object', 'class'];

    /** @var int */
    private int $count = 0;

    /** @var ?array<int> */
    private ?array $ids = null;

    /** @var ?array<array|object> */
    private ?array $rows = null;

    /**
     * Constructor.
     * @param  PDO          $pdo
     * @param  PDOStatement $pdoStatement
     * @param  array|null   $options
     * @throws froq\database\ResultException
     */
    public function __construct(PDO $pdo, PDOStatement $pdoStatement, array $options = null)
    {
        if ($pdo->errorCode() == '00000' && $pdoStatement->errorCode() == '00000') {
            // Assign count (affected rows etc).
            $this->count = $pdoStatement->rowCount();

            // Check fetch option.
            if (isset($options['fetch'])) {
                $fetch     = (array) $options['fetch'];
                $fetchType = $fetch[0] ?? null;

                switch ($fetchType) {
                    case  'array': $fetchType = PDO::FETCH_ASSOC; break;
                    case 'object': $fetchType = PDO::FETCH_OBJ;   break;
                    case  'class':
                        $fetchClass = $fetch[1] ?? null;
                        if (!$fetchClass) {
                            throw new ResultException(
                                'No fetch class given, it is required when fetch type '.
                                'is `class` [tip: give it as second item of `fetch` option]'
                            );
                        }

                        $fetchType = PDO::FETCH_CLASS;
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

            $query = ltrim($pdoStatement->queryString);

            // Select queries & returning clauses (https://www.postgresql.org/docs/current/dml-returning.html).
            if (stripos($query, 'SELECT') !== false || (
                stripos($query, 'RETURNING') && preg_match('~^INSERT|UPDATE|DELETE~i', $query)
            )) {
                // Set or get default.
                $fetchType ??= $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);

                $this->rows = (
                    ($fetchType == PDO::FETCH_CLASS)
                        ? $pdoStatement->fetchAll($fetchType, $fetchClass)
                        : $pdoStatement->fetchAll($fetchType)
                ) ?: null;

                // Indexing by given index field.
                if (isset($options['index']) && $this->rows) {
                    $index = $options['index'];
                    if (!array_key_exists($index, (array) $this->rows[0])) {
                        throw new ResultException('Given index `%s` not found in row set', $index);
                    }

                    $rows  = [];
                    $array = is_array($this->rows[0]);
                    foreach ($this->rows as $row) {
                        $rows[$array ? $row[$index] : $row->{$index}] = $row;
                    }

                    // Re-assign.
                    $this->rows = $rows;
                }
            }

            // Sequence option to prevent transaction errors that comes from lastInsertId() calls
            // but while commit() returning true when sequence field not exists. Default is true
            // for "INSERT" queries.
            $sequence = (bool) ($options['sequence'] ?? true);

            // Insert queries.
            if ($sequence && stripos($query, 'INSERT') === 0) {
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

                    $this->ids = $ids;
                }
            }
        }
    }

    /**
     * @inheritDoc froq\common\interface\Arrayable
     */
    public function toArray(): array
    {
        return $this->rows ?? [];
    }

    /**
     * @inheritDoc froq\common\interface\Listable
     */
    public function toList(): array
    {
        return array_list($this->toArray());
    }

    /**
     * @inheritDoc froq\common\interface\Objectable
     */
    public function toObject(): object
    {
        return (object) array_map('object', $this->toArray());
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
        $rows = [];

        if (!$ctor) {
            foreach ($this->toArray() as $row) {
                $class = new $class(...$ctorArgs);
                foreach ($row as $name => $value) {
                    $class->{$name} = $value;
                }
                $rows[] = $class;
            }
        } else {
            foreach ($this->toArray() as $row) {
                $rows[] = new $class($row, ...$ctorArgs);
            }
        }

        return $rows;
    }

    /**
     * Create a collection with rows.
     *
     * @return froq\collection\Collection
     * @since  5.0
     */
    public function toCollection(): Collection
    {
        return new Collection($this->rows);
    }

    /**
     * Get last insert id when available.
     *
     * @return int|null
     */
    public function id(): int|null
    {
        return last($this->ids ?? []);
    }

    /**
     * Get all insert ids or one when available.
     *
     * @param  int|null $i
     * @return int|array<int>|null
     */
    public function ids(int $i = null): int|array|null
    {
        return ($i === null) ? $this->ids : $this->ids[$i] ?? null;
    }

    /**
     * Get one row.
     *
     * @param  int $i
     * @return array|object|null
     */
    public function row(int $i): array|object|null
    {
        return $this->rows[$i] ?? null;
    }

    /**
     * Get all rows or one.
     *
     * @param  int|null $i
     * @return array<array|object>|array|object|null
     */
    public function rows(int $i = null): array|object|null
    {
        return ($i === null) ? $this->rows : $this->rows[$i] ?? null;
    }

    /**
     * Get first row.
     *
     * @return array|object|null
     */
    public function first(): array|object|null
    {
        return first($this->toArray());
    }

    /**
     * Get last row.
     *
     * @return array|object|null
     */
    public function last(): array|object|null
    {
        return last($this->toArray());
    }

    /**
     * Each.
     *
     * @param  callable $func
     * @return self
     * @since  5.4
     */
    public function each(callable $func): self
    {
        each($this->toArray(), $func);

        return $this;
    }

    /**
     * Filter.
     *
     * @param  callable $func
     * @return self
     * @since  5.0
     */
    public function filter(callable $func): self
    {
        $this->rows = array_filter($this->toArray(), $func);

        return $this;
    }

    /**
     * Map.
     *
     * @param  callable $func
     * @return self
     * @since  5.0
     */
    public function map(callable $func): self
    {
        $this->rows = array_map($func, $this->toArray());

        return $this;
    }

    /**
     * Reduce.
     *
     * @param  mixed    $carry
     * @param  callable $func
     * @return mixed
     * @since  5.0
     */
    public function reduce(mixed $carry, callable $func): mixed
    {
        return array_reduce($this->toArray(), $func, $carry);
    }

    /**
     * Reverse.
     *
     * @return self
     * @since  6.0
     */
    public function reverse(): self
    {
        $this->rows = array_reverse($this->toArray());

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
     */ #[\ReturnTypeWillChange]
    public function getIterator(): iterable
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public function offsetExists(mixed $i): bool
    {
        return $this->row($i) !== null;
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public function offsetGet(mixed $i): array|object|null
    {
        return $this->row($i);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     ReadonlyClassError
     */
    public function offsetSet(mixed $i, mixed $_): never
    {
        throw new \ReadonlyClassError($this);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     ReadonlyClassError
     */
    public function offsetUnset(mixed $i): never
    {
        throw new \ReadonlyClassError($this);
    }
}
