<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\ResultException;
use froq\collection\Collection;
use PDO, PDOStatement, PDOException, ArrayIterator, Countable, IteratorAggregate, ArrayAccess;

/**
 * Result.
 *
 * @package froq\database
 * @object  froq\database\Result
 * @author  Kerem Güneş
 * @since   4.0
 */
final class Result implements Countable, IteratorAggregate, ArrayAccess
{
    /** @var int */
    private int $count = 0;

    /** @var array<int>|null */
    private array|null $ids = null;

    /** @var array<array|object>|null */
    private array|null $rows = null;

    /** @var array @since 5.0 */
    private static array $fetchTypes = ['array', 'object', 'class'];

    /**
     * Constructor.
     * @param PDO          $pdo
     * @param PDOStatement $pdoStatement
     * @param array|null   $options
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
                        $fetchClass || throw new ResultException(
                            'No fetch class given, it is required when fetch type is `class`'
                        );

                        $fetchType = PDO::FETCH_CLASS;
                        break;
                    default:
                        if ($fetchType && !in_array($fetchType, self::$fetchTypes)) {
                            throw new ResultException('Invalid fetch type `%s`, valids are: %s',
                                [$fetchType, join(', ', self::$fetchTypes)]
                            );
                        }

                        unset($fetchType);
                }
            }

            // Set/update sequence option to prevent transaction errors that comes from lastInsertId()
            // calls but while commit() returning true when sequence field not exists.
            $sequence = true;
            if (isset($options['sequence'])) {
                $sequence = (bool) $options['sequence'];
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
                if (isset($options['index']) && $this->rows != null) {
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

        // Flush.
        $pdoStatement = null;
    }

    /**
     * Get rows as array.
     *
     * @return array<array|object>
     */
    public function toArray(): array
    {
        return $this->rows ?? [];
    }

    /**
     * Get rows as object.
     *
     * @return array<object>
     */
    public function toObject(): array
    {
        $rows = [];

        foreach ($this->toArray() as $row) {
            $rows[] = (object) $row;
        }

        return $rows;
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
     * Create a collection with rows, for map/filter etc.
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
        $ids = $this->ids ?? [];

        return end($ids) ?: null;
    }

    /**
     * Get all insert ids when available.
     *
     * @return array<int>|null
     */
    public function ids(): array|null
    {
        return $this->ids ?? null;
    }

    /**
     * Get a single row.
     *
     * @param  int $i
     * @return array|object|null
     */
    public function row(int $i): array|object|null
    {
        return $this->rows[$i] ?? null;
    }

    /**
     * Get all rows.
     *
     * @param  int|null $i
     * @return array<array|object>|object|null
     */
    public function rows(int $i = null): array|object|null
    {
        if ($i !== null) {
            return $this->row($i);
        }
        return $this->rows;
    }

    /**
     * Get first row.
     *
     * @return array|object|null
     */
    public function first(): array|object|null
    {
        return $this->rows ? current($this->rows) : null;
    }

    /**
     * Get last row.
     *
     * @return array|object|null
     */
    public function last(): array|object|null
    {
        return $this->rows ? end($this->rows) : null;
    }

    /**
     * Filter.
     *
     * @param  callable $func
     * @param  bool     $keepKeys
     * @return self
     * @since  5.0
     */
    public function filter(callable $func, bool $keepKeys = false): self
    {
        // Stay in here.
        $func = $func->bindTo($this, $this);

        $this->rows = $this->toCollection()->filter($func, $keepKeys)->toArray();

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
        // Stay in here.
        $func = $func->bindTo($this, $this);

        $this->rows = $this->toCollection()->map($func)->toArray();

        return $this;
    }

    /**
     * Reduce.
     *
     * @param  any      $carry
     * @param  callable $func
     * @return any
     * @since  5.0
     */
    public function reduce($carry, callable $func)
    {
        // Stay in here.
        $func = $func->bindTo($this, $this);

        return $this->toCollection()->reduce($carry, $func);
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
    public function getIterator(): iterable
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public function offsetExists($i)
    {
        return isset($this->rows[$i]);
    }

    /**
     * @inheritDoc ArrayAccess
     */
    public function offsetGet($i)
    {
        return $this->row($i);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     froq\database\ResultException
     */
    public function offsetSet($i, $row)
    {
        throw new ResultException('No set() allowed for ' . $this::class);
    }

    /**
     * @inheritDoc ArrayAccess
     * @throws     froq\database\ResultException
     */
    public function offsetUnset($i)
    {
        throw new ResultException('No unset() allowed for ' . $this::class);
    }
}
