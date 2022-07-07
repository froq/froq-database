<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\database\{Database, DatabaseRegistry, DatabaseRegistryException, Query};
use froq\database\{common\Table, trait\RecordTrait};
use froq\validation\ValidationError;
use froq\common\trait\StateTrait;
use State;

/**
 * A class, mimics "Active Record" pattern and may be extended by many record classes
 * to handle CRUD operations in a safe way via `$form` property with validation.
 *
 * @package froq\database\record
 * @object  froq\database\record\Record
 * @author  Kerem Güneş
 * @since   5.0
 */
class Record implements RecordInterface
{
    use RecordTrait, StateTrait;

    /** @var froq\database\record\Form */
    protected Form $form;

    /** @var string */
    protected string $formClass;

    /** @var froq\database\Query */
    protected Query $query;

    /** @var int|string|null */
    private int|string|null $id;

    /**
     * Constructor.
     *
     * @param  froq\database\Database|null            $db
     * @param  string|froq\database\common\Table|null $table
     * @param  string|froq\database\record\Form|null  $form
     * @param  array|null                             $data
     * @param  array|null                             $options
     * @param  array|null                             $validations
     */
    public function __construct(Database $db = null, string|Table $table = null, string|Form $form = null,
        array $data = null, array $options = null, array $validations = null)
    {
        // Try to use active database when none given.
        $this->db = $db ?? DatabaseRegistry::getDefault();

        $data && $this->data = $data;

        $this->query = new Query($this->db);
        $this->state = new State();

        if ($table) {
            if ($table instanceof Table) {
                $this->table = $table;
            } else {
                $this->table = new Table($table);
            }
            $this->query->table((string) $this->table->getName());
        }

        if ($form) {
            if ($form instanceof Form) {
                $this->form      = $form;
                $this->formClass = $form::class;
            } else {
                $this->formClass = $form;
            }
        }

        $this->setOptions($options)->setValidations($validations);
    }

    /**
     * Clone.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
        $this->query->reset();

        $this->state = clone $this->state;

        if (isset($this->table)) {
            $this->table = clone $this->table;
            $this->query->table((string) $this->table->getName());
        }

        if (isset($this->form)) {
            $this->form = clone $this->form;
        }

        $this->validation = clone $this->validation;
        $this->validation->reset();
    }

    /**
     * Set form property updating form-class property and form's record.
     *
     * @param  froq\database\record\Form
     * @return self
     */
    public final function setForm(Form $form): self
    {
        $this->form      = $form;
        $this->formClass = $form::class;

        // Prevent recursion, 'cos setRecord() calls setForm() back.
        if ($this->form->getRecord() !== $this) {
            $this->form->setRecord($this);
        }

        return $this;
    }

    /**
     * Get form property.
     *
     * @return froq\database\record\Form|null
     */
    public final function getForm(): Form|null
    {
        return $this->form ?? null;
    }

    /**
     * Set form-class property.
     *
     * @param  string $formClass
     * @return self
     */
    public final function setFormClass(string $formClass): self
    {
        if (!class_exists($formClass)) {
            throw new RecordException('Given form class `%s` not exists', $formClass);
        }
        if (!class_extends($formClass, Form::class)) {
            throw new RecordException('Given form class `%s` must extend class `%s`',
                [$formClass, Form::class]);
        }

        $this->formClass = $formClass;

        return $this;
    }

    /**
     * Get form-class property.
     *
     * @return string|null $formClass
     */
    public final function getFormClass(): string|null
    {
        return $this->formClass ?? null;
    }

    /**
     * Get form's data property if form property exists.
     *
     * @return array|null
     */
    public final function getFormData(): array|null
    {
        return $this->getForm()?->getData();
    }

    /**
     * Get a form instance setting and returning self form or creating new one from provided
     * form class or default.
     *
     * @return froq\database\record\Form
     */
    public final function getFormInstance(): Form
    {
        // Use internal or self form/form class if available.
        $form = $this->form ?? $this->formClass ?? new Form(
            db: $this->db, record: $this,
            data: $this->getData(), table: $this->getTable(),
            options: $this->getOptions(), validations: $this->getValidations()
        );

        // If form is a class.
        if (is_string($form)) {
            // Checks also class validity.
            $this->setFormClass($form);

            // Init & update form.
            $this->setForm($form = new $form());
        }

        return $form;
    }

    /**
     * Set id.
     *
     * @param  int|string|null $id
     * @return self
     */
    public final function setId(int|string|null $id): self
    {
        return $this->id($id);
    }

    /**
     * Get id.
     *
     * @return int|string|null
     */
    public final function getId(): int|string|null
    {
        return $this->id();
    }

    /** State getters. */
    public final function saved() { return $this->state->saved; }
    public final function finded() { return $this->state->finded; }
    public final function removed() { return $this->state->removed; }

    /** Aliases. */
    public final function okay(&...$args) { return $this->isValid(...$args); }
    public final function found() { return $this->isFinded(); }

    /**
     * Proxy method to self form for validation processes.
     *
     * @param  array|null &$data
     * @param  array|null &$errors
     * @param  array|null  $options
     * @return bool
     */
    public final function isValid(array &$data = null, array &$errors = null, array $options = null): bool
    {
        return $this->getFormInstance()->isValid($data, $errors, $options);
    }

    /**
     * Check saved state, fill given id argument when primary exists on record.
     *
     * @param  int|string|null &$id
     * @return bool
     */
    public final function isSaved(int|string &$id = null): bool
    {
        $id = $this->id ?? null;

        return (bool) $this->saved();
    }

    /**
     * Check finded state.
     *
     * @return bool
     */
    public final function isFinded(): bool
    {
        return (bool) $this->finded();
    }

    /**
     * Check removed state.
     *
     * @return bool
     */
    public final function isRemoved(): bool
    {
        return (bool) $this->removed();
    }

    /**
     * Apply where condition for find/remove actions.
     *
     * Note: query will always contain primary value(s) as first statement and
     * continue with "AND" operator.
     *
     * @param  array|string $where
     * @param  array|null   $params
     * @param  string|null  $op
     * @return self
     */
    public final function where(array|string $where, array $params = null, string $op = null): self
    {
        $this->query->where($where, $params, $op);

        return $this;
    }

    /**
     * Apply returning clause for insert/update/delete actions.
     *
     * @param  string|array<string>|bool $fields
     * @param  string|null               $fetch
     * @return self
     */
    public final function return(string|array|bool $fields, string $fetch = null): self
    {
        $this->query->return($fields, $fetch);

        return $this;
    }

    /**
     * Apply conflict clause for insert/update actions.
     *
     * @param  string     $fields
     * @param  string     $action
     * @param  array|null $update
     * @param  array|null $where
     * @return self
     */
    public final function conflict(string $fields, string $action, array $update = null, array $where = null): self
    {
        $this->query->conflict($fields, $action, $update, $where);

        return $this;
    }

    /**
     * Set/get id property and id (primary) field of data array, cause a `RecordException`
     * if no table primary presented yet.
     *
     * @param  int|string|null $id
     * @return int|string|self|null
     */
    public final function id(int|string $id = null): int|string|self|null
    {
        [, $primary] = $this->pack(primary: true);

        if (func_num_args()) {
            $this->id = $id;

            empty($this->data)
                ? $this->data[$primary] = $id
                : $this->data = [$primary => $id] + $this->data; // Put to top primary.

            return $this;
        }

        return $this->id ?? $this->data[$primary] ?? null;
    }

    /**
     * Save given or self data to target table, set `saved` state, set `$id` property if
     * table primary was presented, throw a `RecordException` if no data given yet or null
     * primary given for update, or throw a `ValidationError` if validation fails.
     *
     * Note: After this method, `isSaved()` method must be called to check `saved` state.
     *
     * @param  array|null        &$data
     * @param  array|null        &$errors
     * @param  array|null         $options
     * @param  string|array|null  $select
     * @param  string|array|null  $drop
     * @param  bool|null          $validate @internal
     * @return self
     * @throws froq\database\record\RecordException
     */
    public final function save(array &$data = null, array &$errors = null, array $options = null,
        string|array $select = null, string|array $drop = null, bool $validate = null): self
    {
        // Update data, not set all.
        if ($data !== null) {
            $this->updateData($data);
        }

        $data ??= $this->getData() ?: $this->getFormData();
        $data || throw new RecordException(
            'No data yet, call setData() or pass $data argument to save()'
        );

        [$table, $primary] = $this->pack();

        // When primary given id() or setId() before.
        if ($primary && isset($this->data[$primary])) {
            $data[$primary] = $this->data[$primary];
        }

        // Options are used for only save actions.
        $options = [...$this->options, ...$options ?? []];

        // Default is true (@see froq\database\trait\RecordTrait).
        $validate ??= (bool) $options['validate'];

        // Run validation.
        if ($validate && !$this->isValid($data, $errors, $options)) {
            throw new ValidationError(
                'Cannot save record (%s), validation failed [tip: %s]',
                [static::class, ValidationError::tip()], errors: $errors
            );
        }

        // Detect insert/update.
        $new = !isset($primary) || !isset($data[$primary]);

        if (!$new) {
            // Get id & check empty.
            $id = $data[$primary] ?? null;
            $id ?? throw new RecordException('Null primary value');
        } else {
            unset($data[$primary]);
        }

        // Run macro for insert/update.
        $run = fn() => (
            $new ? $this->doInsert($data, $table, $primary, $options)
                 : $this->doUpdate($data, $table, $primary, $options, $id)
        );

        $data = $options['transaction'] ? $this->db->transaction($run) : $run();

        // When select whole/fresh data wanted (works with primary's only).
        if (isset($options['select']) || $select) {
            $select = $options['select'] ?? $select;
            $select && $data = (array) $this->db->select($table, $select, [$primary => $data[$primary]]);
        }

        // Drop unwanted fields.
        if (isset($options['drop']) || $drop) {
            $fields = $options['drop'] ?? $drop;

            // Collect null field keys.
            if ($fields === '@null') {
                $fields = array_keys(array_filter($data, 'is_null'));
            }

            // Comma-separated list.
            $fields = is_string($fields) ? split('\s*,\s*', $fields) : $fields;
            foreach ((array) $fields as $field) {
                unset($data[$field]);
            }
        }

        // Update data on both record & form.
        $this->setData($data);
        if ($form = $this->getForm()) {
            $form->setData($data);
        }

        return $this;
    }

    /**
     * Find and get a record from self table by given id or self id, set `finded` state,
     * throw a `RecordException` if id is null or cause a `RecordException` if no table
     * primary presented.
     *
     * Note: After this method `isFinded()` method must be called to check `finded` state.
     *
     * @param  int|string|null   $id
     * @param  array|string|null $fields
     * @return froq\database\record\Record
     * @throws froq\database\record\RecordException
     */
    public final function find(int|string $id = null, array|string $fields = null): Record
    {
        $id ??= $this->id();
        $id ?? throw new RecordException('Null primary value');

        $that = $this->findAll([$id], $fields)[0] ?? (clone $this);

        // Required for changing data list => map (thats copy).
        if ($this !== $that) $this->setData($that->getData());

        return $that;
    }

    /**
     * Find and get all records from self table by given ids, set `finded` state,
     * throw a `RecordException` if ids are empty or cause a `RecordException` if
     * no table primary presented.
     *
     * @param  array<int|string> $ids
     * @param  array|string|null $fields
     * @return froq\database\record\RecordList
     * @throws froq\database\record\RecordException
     */
    public final function findAll(array $ids, array|string $fields = null): RecordList
    {
        [$table, $primary, $ids] = $this->pack($ids, primary: true);

        $ids || throw new RecordException('Null primary values');

        $query = $this->query($table)->equal($primary, [$ids]);

        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $fields = $fields ?: $this->query->pull('return.fields') ?: '*';
        $result = $query->select(select: $fields)->run(fetch: 'array');

        // Copy with rows & "finded" state.
        $thats = $this->copy($result->rows(), state: ['finded', $result->count()]);

        return new RecordList($thats);
    }

    /**
     * Remove a record from self table by given id or self id, set `removed` state,
     * throw a `RecordException` if id is null or cause a `RecordException` if no
     * table primary presented.
     *
     * Note: After this method `isRemoved()` method must be called to check `removed` state.
     *
     * @param  int|string|null   $id
     * @param  string|array|null $return
     * @return froq\database\record\Record
     * @throws froq\database\record\RecordException
     */
    public final function remove(int|string $id = null, string|array $return = null): Record
    {
        $id ??= $this->id();
        $id ?? throw new RecordException('Null primary value');

        $that = $this->removeAll([$id], $return)[0] ?? (clone $this);

        // Required for changing data list => map (thats copy).
        if ($this !== $that) $this->setData($that->getData());

        return $that;
    }

    /**
     * Remove all records from self table by given ids, set `removed` state, throw
     * a `RecordException` if ids are empty or cause a `RecordException` if no table
     * primary presented.
     *
     * @param  array<int|string> $ids
     * @param  string|array|null $return
     * @return froq\database\record\RecordList
     * @throws froq\database\record\RecordException
     */
    public final function removeAll(array $ids, string|array $return = null): RecordList
    {
        [$table, $primary, $ids] = $this->pack($ids, primary: true);

        $ids || throw new RecordException('Null primary values');

        $query = $this->query($table)->equal($primary, [$ids]);

        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        // When no "return" given, an empty list returns even it was successful.
        $return = $return ?: $this->query->pull('return.fields') ?: $primary;
        $result = $query->delete(return: $return)->run(fetch: 'array');

        // Copy with rows & "removed" state.
        $thats = $this->copy($result->rows(), state: ['removed', $result->count()]);

        return new RecordList($thats);
    }

    /**
     * Find multiple records by given arguments returning a `RecordList` filled by
     * found records.
     *
     * @param  string|array $where
     * @param  mixed     ...$selectArgs For select() method.
     * @return froq\database\record\RecordList
     */
    public final function findBy(string|array $where, mixed ...$selectArgs): RecordList
    {
        $rows = $this->select($where, ...$selectArgs);

        // For single records.
        if (value($selectArgs, 'limit') == 1) {
            $rows = [(array) $rows];
        }

        // Copy with rows & "finded" state.
        $thats = $this->copy($rows, state: ['finded', size($rows)]);

        return new RecordList($thats);
    }

    /**
     * Remove multiple records by given arguments returning a `RecordList` filled by
     * removed records.
     *
     * @param  string|array $where
     * @param  mixed     ...$deleteArgs For delete() method.
     * @return froq\database\record\RecordList
     */
    public final function removeBy(string|array $where, mixed ...$deleteArgs): RecordList
    {
        $rows = $this->delete($where, ...$deleteArgs);

        // Copy with rows & "removed" state.
        $thats = $this->copy($rows, state: ['removed', size($rows)]);

        return new RecordList($thats);
    }

    /**
     * Select record(s) from self table by given conditions.
     *
     * @param  string|array $where
     * @param  array|null   $params
     * @param  string|null  $op
     * @param  string|array $fields
     * @param  int|null     $limit
     * @param  int|null     $offset
     * @param  string|null  $order
     * @param  string|null  $fetch
     * @return array|object|null
     */
    public final function select(string|array $where, array $params = null, string $op = null, string|array $fields = '*',
        int $limit = null, int $offset = null, string $order = null, string $fetch = null): array|object|null
    {
        // If return() called before, simply overrides fields.
        $return = $this->query->pull('return.fields');
        $return && $fields = $return;

        $query = $this->query()->select($fields);
        $query->where($where, $params, $op);

        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $limit && $query->limit($limit, $offset);
        $order && $query->order($order);

        $result = $query->run($fetch);

        return ($limit == 1) ? $result->rows(0) : $result->rows();
    }

    /**
     * Update record(s) on self table by given conditions.
     *
     * @param  array             $data
     * @param  string|array      $where
     * @param  array|null        $params
     * @param  string|null       $op
     * @param  string|array|null $return
     * @return int|array|null
     */
    public final function update(array $data, string|array $where, array $params = null, string $op = null,
        string|array $return = null): int|array|null
    {
        $query = $this->query()->update($data);
        $query->where($where, $params, $op);

        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $return ??= $this->query->pull('return.fields');
        $return && $query->return($return, fetch: 'array');

        $result = $query->run();

        return $return ? $result->rows() : $result->count();
    }

    /**
     * Delete record(s) from self table by given conditions.
     *
     * @param  string|array      $where
     * @param  array|null        $params
     * @param  string|null       $op
     * @param  string|array|null $return
     * @return int|array|null
     */
    public final function delete(string|array $where, array $params = null, string $op = null,
        string|array $return = null): int|array|null
    {
        $query = $this->query()->delete();
        $query->where($where, $params, $op);

        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $return ??= $this->query->pull('return.fields');
        $return && $query->return($return, fetch: 'array');

        $result = $query->run();

        return $return ? $result->rows() : $result->count();
    }

    /**
     * Count records on self table by given conditions.
     *
     * @param  array       $where
     * @param  array|null  $params
     * @param  string|null $op
     * @return int
     */
    public final function count(string|array $where, array $params = null, string $op = null): int
    {
        $query = $this->query();
        $query->where($where, $params, $op);

        return $query->count();
    }

    /**
     * Pack table, table primary and id/ids stuff, throw a `RecordException` if no table
     * presented or no table primary presented when primary check requested as `true`.
     */
    private function pack(int|string|array $id = null, bool $primary = false): array
    {
        $table = $this->getTable();

        if (!$table || !$table->getName()) {
            throw new RecordException('Empty table for %s, call setTable()', static::class);
        }
        if ($primary && !$table->getPrimary()) {
            throw new RecordException('Empty table primary for %s, call table setPrimary()', static::class);
        }

        // Filter multiple ids by not-null check.
        is_array($id) && $id = array_filter($id, fn($id) => $id !== null);

        return [$table->getName(), $table->getPrimary(), $id];
    }

    /**
     * Create a new Query instance.
     */
    private function query(string $table = null): Query
    {
        $table ??= $this->pack()[0];

        return new Query($this->db, $table);
    }

    /**
     * Copy self data & given state cloning current `Record` instance and return cloned
     * items as list to use for `RecordList` instance.
     *
     * Note: This method does not assign "id" for find, remove etc. to current record object,
     * instead each related id will be assigned to created clone if related data was found.
     */
    private function copy(?array $data, array $state): array
    {
        $this->setState(...$state)->setData((array) $data);

        $thats = [];

        if ($data) {
            $primary = $this->table->getPrimary();

            foreach ($data as $dat) {
                $that = clone $this;
                $that->setState($state[0], 1)->setData($dat = (array) $dat);

                // Assign primary if available.
                if ($primary && isset($dat[$primary])) {
                    $that->id = $dat[$primary];
                }

                $thats[] = $that;
            }
        }

        return $thats;
    }

    /**
     * Do an insert action.
     */
    private function doInsert(array $data, string $table, string|null $primary, array $options): array
    {
        $return   = $options['return']   ?? null;     // Whether returning any field(s) or current data.
        $sequence = $options['sequence'] ?? $primary; // Whether table has sequence or not.

        $query = $this->query($table);
        $query->insert($data, sequence: !!$sequence);

        $return ??= $this->query->pull('return.fields');
        $return && $query->return($return, fetch: 'array');

        $conflict = $this->query->pull('conflict');
        $conflict && $query->conflict(...$conflict);

        $result = $query->run();

        // Get new id if available.
        $id = $result->id();

        $this->state->saved = $result->count();

        // Swap data with returning data.
        $return && $data = (array) $result->first();

        if ($primary && $id) {
            // Put on the top primary.
            $data = [$primary => $id] + $data;

            // Set primary value with new id.
            $this->id($id);
        }

        return $data;
    }

    /**
     * Do an update action.
     */
    private function doUpdate(array $data, string $table, string|null $primary, array $options, int|string $id): array
    {
        $return = $options['return'] ?? null; // Whether returning any field(s) or current data.

        unset($data[$primary]); // Not needed in data set.

        $query = $this->query($table);
        $query->update($data)->equal($primary, $id);

        $return ??= $this->query->pull('return.fields');
        $return && $query->return($return, fetch: 'array');

        $conflict = $this->query->pull('conflict');
        $conflict && $query->conflict(...$conflict);

        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $result = $query->run();

        // Set primary value with given id.
        $this->id($id);

        $this->state->saved = $result->count();

        // Swap data with returning data.
        $return && $data = (array) $result->first();

        // Put on the top primary.
        $data = [$primary => $id] + $data;

        return $data;
    }
}
