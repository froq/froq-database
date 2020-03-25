<?php
/**
 * MIT License <https://opensource.org/licenses/mit>
 *
 * Copyright (c) 2015 Kerem Güneş
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
declare(strict_types=1);

namespace froq\database;

use froq\database\{ResultException};
use PDO, PDOStatement, Countable, IteratorAggregate, ArrayIterator;

/**
 * Result.
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
     * @var ?array
     */
    private ?array $ids = null;

    /**
     * Rows.
     * @var ?array
     */
    private ?array $rows = null;

    /**
     * Constructor.
     * @param PDO                       $pdo
     * @param PDOStatement              $pdoStatement
     * @param string|array<string>|null $fetchOptions
     */
    public function __construct(PDO $pdo, PDOStatement $pdoStatement, $fetchOptions = null)
    {
        if ($pdoStatement->errorCode() == '00000') {
            // Assign count (affected rows etc).
            $this->count = $pdoStatement->rowCount();

            // Select queries.
            if (stripos($pdoStatement->queryString, 'SELECT') === 0) {
                @ [$fetchType, $fetchClass] = (array) $fetchOptions;

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

                $rows = ($fetchType == PDO::FETCH_CLASS)
                    ? $pdoStatement->fetchAll($fetchType, $fetchClass)
                    : $pdoStatement->fetchAll($fetchType);

                $this->rows = $rows ?: null;
            }
            // Insert queries.
            elseif (stripos($pdoStatement->queryString, 'INSERT') === 0) {
                $id = (int) $pdo->lastInsertId();
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

            unset($pdoStatement);
        }
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
    public function toClass(string $class, bool $ctor = false, array $ctorArgs): array
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
     * @return ?array<int, array|object>
     */
    public function rows(): ?array
    {
        return $this->rows ?? null;
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
