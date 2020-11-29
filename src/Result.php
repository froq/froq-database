<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\ResultException;
use froq\collection\Collection;
use PDO, PDOStatement, PDOException, Countable, IteratorAggregate, ArrayIterator;

/**
 * Result.
 *
 * @package froq\database
 * @object  froq\database\Result
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 */
final class Result implements Countable, IteratorAggregate
{
    /**
     * Count.
     * @var int
     */
    private int $count = 0;

    /**
     * Ids.
     * @var ?array<int>
     */
    private ?array $ids = null;

    /**
     * Rows.
     * @var ?array<array|object>
     */
    private ?array $rows = null;

    /**
     * Constructor.
     * @param PDO           $pdo
     * @param PDOStatement  $pdoStatement
     * @param array|null    $options
     */
    public function __construct(PDO $pdo, PDOStatement $pdoStatement, array $options = null)
    {
        if ($pdoStatement->errorCode() == '00000') {
            // Assign count (affected rows etc).
            $this->count = $pdoStatement->rowCount();

            // Defaults.
            [$fetch, $sequence] = [null, true];

            // Update fetch option if given.
            if (isset($options['fetch'])) {
                @ [$fetchType, $fetchClass] = (array) $options['fetch'];

                switch ($fetchType) {
                    case  'array': $fetchType = PDO::FETCH_ASSOC; break;
                    case 'object': $fetchType = PDO::FETCH_OBJ;   break;
                    case  'class':
                        if (!$fetchClass) {
                            throw new ResultException('No fetch class given, fetch class is required'.
                                ' when fetch type is "class"');
                        } elseif (!class_exists($fetchClass)) {
                            throw new ResultException('No fetch class found such "%s"', [$fetchClass]);
                        }

                        $fetchType = PDO::FETCH_CLASS;
                        break;
                    default:
                        static $fetchTypes = ['array', 'object', 'class'];

                        if ($fetchType && !in_array($fetchType, $fetchTypes)) {
                            throw new ResultException('Invalid fetch type "%s" given, valids are: %s',
                                [$fetchType, join(', ', $fetchTypes)]);
                        }

                        $fetchType = $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
                }
            }

            // Update sequence option to prevent transaction errors that comes from lastInsertId()
            // calls but while commit() returning true when sequence field not exists.
            if (isset($options['sequence'])) {
                $sequence = (bool) $options['sequence'];
            }

            $query = trim($pdoStatement->queryString);

            // Select queries & Returning clauses (https://www.postgresql.org/docs/current/dml-returning.html).
            if (stripos($query, 'SELECT') === 0 || (
                stripos($query, 'RETURNING') && preg_match('~^INSERT|UPDATE|DELETE~i', $query)
            )) {
                // Set or get default.
                $fetchType ??= $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);

                $rows = ($fetchType == PDO::FETCH_CLASS)
                      ? $pdoStatement->fetchAll($fetchType, $fetchClass)
                      : $pdoStatement->fetchAll($fetchType);

                $this->rows = $rows ?: null;
            }

            // Insert queries.
            if ($sequence && stripos($query, 'INSERT') === 0) {
                $id = null;

                // Prevent "SQLSTATE[55000]: Object not in prerequisite state: 7 ..." error that mostly
                // occurs when a user-provided ID given to insert data. Sequence option for this but cannot
                // prevent transaction commits when no sequence field exists.
                try {
                    $id = (int) $pdo->lastInsertId();
                } catch (PDOException $e) {}

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
     * To array.
     * @return array<int, array>
     */
    public function toArray(): array
    {
        return $this->rows ?? [];
    }

    /**
     * To object.
     * @return array<int, object>
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
     * To class.
     * @param  string $class
     * @param  bool   $ctor
     * @param  array  $ctorArgs
     * @return array<int, class>
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
     * To collection, for map/filter etc.
     *
     * @return froq\collection\Collection
     * @since  5.0
     */
    public function toCollection(): Collection
    {
        return new Collection($this->rows);
    }

    /**
     * Id.
     * @return ?int
     */
    public function id(): ?int
    {
        $ids = $this->ids ?? [];

        return end($ids) ?: null;
    }

    /**
     * Ids.
     * @return ?array<int>
     */
    public function ids(): ?array
    {
        return $this->ids ?? null;
    }

    /**
     * Row.
     * @param  int $i
     * @return ?array|?object
     */
    public function row(int $i)
    {
        // Reverse, eg: -1 for last.
        if ($i < 0) {
            $i = $this->count + $i;
        }

        return $this->rows[$i] ?? null;
    }

    /**
     * Rows.
     * @return ?array<array|object>
     */
    public function rows(): ?array
    {
        return $this->rows ?? null;
    }

    /**
     * First.
     * @return ?array|?object
     */
    public function first()
    {
        return $this->row(0);
    }

    /**
     * Last.
     * @return ?array|?object
     */
    public function last()
    {
        return $this->row(-1);
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
}
