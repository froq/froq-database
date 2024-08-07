<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\entry;

use froq\database\{Database, Query, Result};
use froq\common\interface\Arrayable;
use State, Throwable;

/**
 * Base Entry class for managing entry data and queries.
 *
 * All properties are private to provide an ease for to set data fields dynamically.
 *
 * Example: A `Book` entry can be declared and used as below, to use in repositories.
 *
 * ```
 * class Book extends Entry {
 *   function find(int $id): {
 *     $this->query('books')->select('*')->id($id);
 *
 *     // Required to reset old data set by create() etc.
 *     $this->reset();
 *
 *     $this->commit();
 *
 *     // Found tick for state.
 *     $this->okay(isset($this->id));
 *   }
 *
 *   function create(array $data): {
 *     $this->query('books')->insert($data);
 *     $this->commit();
 *
 *     // If no RETURNING supported by database system.
 *     $this->find($this->result()->id() ?? 0);
 *   }
 * }
 *
 * $book = (new Book())->create($data);
 * return $book->okay() ? $book->toArray() : null;
 * ```
 *
 * @package froq\database\entry
 * @class   froq\database\entry\Entry
 * @author  Kerem Güneş
 * @since   7.1
 */
abstract class Entry implements Arrayable
{
    /** Query instance. */
    private Query $query;

    /** Result after persist. */
    private Result $result;

    /** Manager instance. */
    private EntryManager $manager;

    /** Data holder. */
    private EntryData $data;

    /** Dynamic state. */
    private State $state;

    public function __construct(iterable $data = null, Database $db = null)
    {
        $this->manager = new EntryManager($db);
        $this->data    = new EntryData($data ?? []);
        $this->state   = new State();
    }

    /**
     * Dynamic data setter as property.
     *
     * @param  string $field
     * @param  mixed  $value
     * @return void
     */
    public function __set(string $field, mixed $value): void
    {
        $this->data->set($field, $value);
    }

    /**
     * Dynamic data getter as property.
     *
     * @param  string $field
     * @return mixed
     */
    public function __get(string $field): mixed
    {
        return $this->data->get($field);
    }

    /**
     * Dynamic data checker as property.
     *
     * @param  string $field
     * @return bool
     */
    public function __isset(string $field): bool
    {
        return $this->data->has($field);
    }

    /**
     * Dynamic data remover as property.
     *
     * @param  string $field
     * @return void
     */
    public function __unset(string $field): void
    {
        $this->data->remove($field);
    }

    /**
     * Create & get query property.
     *
     * @param  string|null $table
     * @return froq\database\Query
     */
    public function query(string $table = null): Query
    {
        $this->query = new Query($this->manager->db(), $table);

        return $this->query;
    }

    /**
     * Get result property.
     *
     * @return froq\database\Result|null
     */
    public function result(): Result|null
    {
        return $this->result ?? null;
    }

    /**
     * Get manager property.
     *
     * @return froq\database\entry\EntryManager
     */
    public function manager(): EntryManager
    {
        return $this->manager;
    }

    /**
     * Get data property.
     *
     * @return froq\database\entry\EntryData
     */
    public function data(): EntryData
    {
        return $this->data;
    }

    /**
     * State setter / getter.
     *
     * @param  string|null $name
     * @param  mixed|null  $value
     * @return mixed
     */
    public function state(string $name = null, mixed $value = null): mixed
    {
        return match (func_num_args()) {
            0 => $this->state,
            1 => $this->state->get($name),
            2 => $this->state->set($name, $value),
        };
    }

    /**
     * Set / get okay state.
     *
     * @param  bool|null $okay
     * @return bool|null
     */
    public function okay(bool $okay = null): bool|null
    {
        if (func_num_args()) {
            $this->state->okay = $okay;
        }

        return $this->state->okay;
    }

    /**
     * Set / get action state.
     *
     * @param  string|null $action
     * @return string|null
     */
    public function action(string $action = null): string|null
    {
        if (func_num_args()) {
            $this->state->action = $action;
        }

        return $this->state->action;
    }

    /**
     * Persist / retrieve this object sending its data to database by attaching & detaching
     * it and calling its entry manager object, then assign its `$result` property that
     * returned from commit call and re-set its data with this result data if any.
     *
     * Note: The queries used in Entry classes should call `Query::return()` method to
     * update the data of these entry instances if `RETURNING` clause is supported by
     * database system. Otherwise a `find()` method can be defined for this purpose,
     * and don't forget to call `reset()` method to reset insert/update data while
     * doing this.
     *
     * @return self
     */
    public function commit(): self
    {
        if (!isset($this->query)) {
            throw new EntryException(
                'No query yet, call some query methods '.
                'after calling query()'
            );
        }

        try {
            $this->manager->attach($this);
            $this->manager->commit();
            $this->manager->detach($this);

            return $this;
        } catch (Throwable $e) {
            throw new EntryException($e);
        }
    }

    /**
     * Empty all data fields.
     *
     * @return self
     */
    public function reset(): self
    {
        $this->data->empty();

        return $this;
    }

    /**
     * Get some data entries by given fields.
     *
     * @param  array $fields
     * @return array
     */
    public function toData(array $fields): array
    {
        return $this->data->select($fields, combine: true);
    }

    /**
     * Get all data entries.
     *
     * @inheritDoc froq\common\interface\Arrayable
     */
    public function toArray(): array
    {
        return $this->data->toArray();
    }
}
