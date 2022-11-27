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
 * A result class, for query result stuff such as count, ids & rows.
 *
 * @package froq\database
 * @object  froq\database\Result
 * @author  Kerem Güneş
 * @since   4.0
 */
final class Result implements Arrayable, \Countable, \IteratorAggregate, \ArrayAccess
{
    /** @var int */
    private int $count = 0;

    /** @var froq\database\result\Ids<int> */
    private Ids $ids;

    /** @var froq\database\result\Rows<array|object> */
    private Rows $rows;

    /**
     * Constructor.
     *
     * @param  PDO           $pdo
     * @param  PDOStatement  $pdoStatement
     * @param  array|null    $options
     * @param  Database|null $db
     * @throws froq\database\ResultException
     */
    public function __construct(PDO $pdo, PDOStatement $pdoStatement, array $options = null, Database $db = null)
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
            match ($options['fetch']) {
                'array'  => $fetchType = PDO::FETCH_ASSOC,
                'object' => $fetchType = PDO::FETCH_OBJ,
                default  => [
                    // All others are as class.
                    $fetchType  = PDO::FETCH_CLASS,
                    $fetchClass = class_exists($options['fetch']) ? $options['fetch'] :
                        throw new ResultException('Fetch class %q not found', $options['fetch'])
                ]
            };
        }

        // Assign count (affected rows etc).
        $this->count = $count = $pdoStatement->rowCount();

        // Note: Sequence option to prevent transaction errors that comes from lastInsertId()
        // calls but while commit() returning true when sequence field not exists. Default is
        // true for "INSERT" queries if no "sequence" option given.
        $sequence = $options['sequence'] && preg_match('~^\s*INSERT~i', $pdoStatement->queryString);

        // Insert queries.
        if ($pdoStatement->rowCount() && $sequence) {
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
                if ($count > 1) {
                    // MySQL awesomeness, last id is first id..
                    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
                        $start = $id;
                        $end   = $id + $count - 1;
                    } else {
                        $start = $id - $count + 1;
                        $end   = $id;
                    }

                    $ids = range($start, $end);
                }

                // Update count, in case.
                $this->count = count($ids);

                $this->ids->add(...$ids);
                unset($ids);
            }
        }

        // Select & other fetchable queries (eg: insert/update/delete with returning clause).
        if ($pdoStatement->columnCount()) {
            // Use present type that was set above or get default.
            $fetchType ??= $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);

            // @tome: Data is populated before the constructor is called.
            // To populate data after the constructor use PDO::FETCH_PROPS_LATE.
            $rows = ($fetchType == PDO::FETCH_CLASS)
                  ? $pdoStatement->fetchAll($fetchType|PDO::FETCH_PROPS_LATE, $fetchClass)
                  : $pdoStatement->fetchAll($fetchType);

            if ($rows) {
                // Update count, in case.
                $this->count = count($rows);

                $this->rows->add(...$rows);
                unset($rows);
            }
        }

        // Return for only no "RETURNING" supported databases that send by query builder via
        // run() method. See Query.return() & run().
        if ($count && isset($options['return'])) {
            $this->applyReturnFallback($options['return'], $db);
        }
    }

    /**
     * Get last insert id.
     *
     * @return ?int
     * @since  6.0
     */
    public function getId(): ?int
    {
        return $this->id();
    }

    /**
     * Get a copy of first row.
     *
     * @return ?Row
     * @since  6.0
     */
    public function getRow(): ?Row
    {
        return $this->rows(0, true);
    }

    /**
     * Get a copy of ids property.
     *
     * @return froq\database\result\Ids
     * @since  6.0
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
     * @since  6.0
     */
    public function getRows(bool $init = true): Rows
    {
        if ($init) {
            return (clone $this->rows)
                ->map(fn($row): Row => $this->toRow($row));
        }
        return (clone $this->rows);
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
        return $init && $row ? $this->toRow($row) : $row;
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
            return $init && $row ? $this->toRow($row) : $row;
        }
        $rows = $this->rows->items();
        return $init ? $this->toRows($rows) : $rows;
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
     * @since  6.0
     */
    public function cols(int $index, string|array $field): mixed
    {
        $row = $this->rows($index);

        if ($row && $field != '*') {
            $orow = new Row((array) $row);
            // Single field.
            if (is_string($field)) {
                return $orow->get($field);
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
     * Map all items to given (map-like) class.
     *
     * Note: This method is mate of listTo(), so mapTo() must be called first
     * for listing purposes.
     *
     * @param  string $class
     * @param  array  $classArgs
     * @return self
     * @since  6.0
     */
    public function mapTo(string $class, array $classArgs = []): self
    {
        $object = new $class(...$classArgs);
        foreach ($this->rows as $i => $row) {
            $clone = clone $object;
            foreach ($row as $name => $value) {
                $clone->$name = $value;
            }
            $this->rows[$i] = $clone;
        }

        return $this;
    }

    /**
     * List all items to given (list-like) class.
     *
     * Note: This method is mate of mapTo(), so listTo() must be called last
     * for listing purposes.
     *
     * @param  string $class
     * @param  array  $classArgs
     * @return object
     * @since  6.0
     */
    public function listTo(string $class, array $classArgs = []): object
    {
        $object = new $class(...$classArgs);
        foreach ($this->rows as $row) {
            $object[] = $row;
        }

        return $object;
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
     * @inheritDoc froq\common\interface\Arrayable
     */
    public function toArray(bool $deep = false): array
    {
        return $this->rows->toArray($deep);
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
     * Create a `Rows` instance mapping all items to `Row` instances.
     */
    private function toRows(array $data): Rows
    {
        return new Rows(array_map([$this, 'toRow'], $data));
    }

    /**
     * Apply return fallback that sent by query builder.
     */
    private function applyReturnFallback(array $return, Database $db): void
    {
        $rows = null;

        // When rows given (delete).
        if (isset($return['data'])) {
            $rows = $return['data'];
        }
        // When table given (update/insert).
        elseif (isset($return['table'], $return['fields'])) {
            [$table, $fields, $fetch] = array_select($return, ['table', 'fields', 'fetch']);

            // Update.
            if (isset($return['where'])) {
                $query = $db->initQuery($table);
                foreach ($return['where'] as [$where, $op]) {
                    $query->where($where, op: $op);
                }
                $rows = $query->select($fields)->getAll($fetch);
            }
            // Insert.
            else {
                // Search for primary.
                $rs = $db->executeStatement("SELECT * FROM {$table} LIMIT 1");
                for ($i = 0; $i < $rs->columnCount(); $i++) {
                    $column = $rs->getColumnMeta($i);
                    if ($column['flags'] && in_array('primary_key', $column['flags'])) {
                        $primary = $column['name'];
                        break;
                    }
                } unset($rs); // @free

                if (isset($primary)) {
                    $rows = $db->initQuery($table)
                        ->where($primary, [$this->ids->toArray()])
                        ->select($fields)->getAll($fetch);
                }
            }
        }

        // Fill rows with returning data.
        if ($rows) {
            $this->count = count($rows);

            $this->rows->add(...$rows);
            unset($rows);
        }
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

        $options['sequence'] = (bool) ($options['sequence'] ?? true);

        return $options;
    }
}
