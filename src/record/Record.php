<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\database\record\{RecordException, RecordInterface, RecordList, Form, FormException};
use froq\database\{Database, Query, trait\RecordTrait};
use froq\validation\ValidationError;
use froq\pager\Pager;

/**
 * Record.
 *
 * Represents a record class that mimics "Active Record" pattern and may be extended by many
 * record classes to handle CRUD operations in a safe way via form object with validation.
 *
 * @package froq\database\record
 * @object  froq\database\record\Record
 * @author  Kerem Güneş
 * @since   5.0
 */
class Record implements RecordInterface
{
    /** @see froq\database\trait\RecordTrait */
    use RecordTrait;

    /** @var froq\database\record\Form */
    protected Form $form;

    /** @var string */
    protected string $formClass;

    /** @var froq\database\Query */
    protected Query $query;

    /** @var int|string|null */
    private int|string|null $id;

    /** @var bool */
    private bool $saved;

    /** @var int */
    private int $finded;

    /** @var int|array|null */
    private int|array|null $removed;

    /**
     * Constructor.
     *
     * @param  froq\database\Database|null           $db
     * @param  string|null                           $table
     * @param  string|null                           $tablePrimary
     * @param  array|null                            $data
     * @param  string|froq\database\record\Form|null $form
     * @param  array|null                            $options
     * @param  array|null                            $validations
     * @param  array|null                            $validationRules
     * @param  array|null                            $validationOptions
     * @throws froq\database\record\FormException
     */
    public function __construct(Database $db = null, string $table = null, string $tablePrimary = null,
        array $data = null, string|Form $form = null, array $options = null, array $validations = null,
        array $validationRules = null, array $validationOptions = null)
    {
        // Try to use active app database object.
        $db ??= function_exists('app') ? app()->database() : throw new RecordException(
            'No database given to deal, be sure running this module with `froq\app` ' .
            'module and be sure `database` option exists in app config or pass $db argument'
        );

        $this->db    = $db;
        $this->query = new Query($db, $table);

        $data && $this->data = $data;

        if ($form != null) {
            if ($form instanceof Form) {
                $this->form      = $form;
                $this->formClass = $form::class;
            } else {
                $this->formClass = $form;
            }
        }

        $this->setOptions($options, self::$optionsDefault);

        // Validations can be combined or simple array'ed.
        if ($validations != null) {
            isset($validations['@rules'])   && $validationRules   = array_pull($validations, '@rules');
            isset($validations['@options']) && $validationOptions = array_pull($validations, '@options');

            // Simple array'ed if no "@rules" field given.
            $validationRules ??= $validations;
        }

        // Set table stuff & validation stuff.
        $table             && $this->table             = $table;
        $tablePrimary      && $this->tablePrimary      = $tablePrimary;
        $validationRules   && $this->validationRules   = $validationRules;
        $validationOptions && $this->validationOptions = $validationOptions;
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
            throw new FormException('Given form class `%s` not exists', $formClass);
        }
        if (!class_extends($formClass, Form::class)) {
            throw new FormException('Given form class `%s` must extend class `%s`',
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
     * Get a form instance setting and returning own or creating new one from provided form class or
     * default.
     *
     * @return froq\database\record\Form
     */
    public final function getFormInstance(): Form
    {
        // Use internal or own (current) form/form class if available.
        $form = $this->form ?? $this->formClass ?? new Form(
            $this->db, $this->getTable(), $this->getTablePrimary(),
            data: $this->getData(), record: $this, options: $this->options,
            validationRules: $this->getValidationRules(), validationOptions: $this->getValidationOptions()
        );

        // If class given.
        if (is_string($form)) {
            // Check also class validity.
            $this->setFormClass($form);

            // Init & update own form.
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

    /**
     * @alias of isValid()
     */
    public final function okay(...$args)
    {
        return $this->isValid(...$args);
    }

    /** State getters. */
    public final function saved() { return $this->saved ?? null; }
    public final function finded() { return $this->finded ?? null; }
    public final function removed() { return $this->removed ?? null; }

    /**
     * @alias of isFinded()
     */
    public final function found(...$args)
    {
        return $this->isFinded(...$args);
    }

    /**
     * Proxy method to own form class for validation processes.
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
     * Check saved state/result, fill given id argument when primary exists on record.
     *
     * @param  int|string|null &$id
     * @return bool
     */
    public final function isSaved(int|string &$id = null): bool
    {
        $id = $this->id ?? null;

        return !!$this->saved();
    }

    /**
     * Check finded state/result, fill given state argument.
     *
     * @param  int|null &$finded
     * @return bool
     */
    public final function isFinded(int &$finded = null): bool
    {
        $finded = $this->finded();

        return !!$finded;
    }

    /**
     * Check removed state/result, fill given state argument.
     *
     * @param  int|array|null &$removed
     * @return bool
     */
    public final function isRemoved(int|array &$removed = null): bool
    {
        $removed = $this->removed();

        return !!$removed;
    }

    /**
     * Create a new query.
     *
     * @return froq\database\Query
     */
    public final function query(): Query
    {
        return new Query($this->db, $this->getTable());
    }

    /**
     * Apply one/many "WHERE" condition/conditions for find/remove actions. Note: query will always contain primary
     * value(s) as first statement and continue with "AND" operator.
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
     * Apply returnin clause for insert/update/delete actions.
     *
     * @param  string|array|bool $fields
     * @param  array|null        $fetch
     * @return self
     */
    public function return(string|array|bool $fields, string|array $fetch = null): self
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
     * Set/get id property and id (primary) field of data array, cause a `RecordException` if no table primary
     * presented yet.
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
     * Save given or own data to target table, set `$saved` property, set `$id` property if table primary was
     * presented, throw a `RecordException` if no data or target table given yet or throw a `ValidationError` if
     * validation fails.
     *
     * @param  array|null        &$data
     * @param  array|null        &$errors
     * @param  array|null         $options
     * @param  string|array|null  $drop
     * @param  bool               $bool
     * @param  bool               $select
     * @param  bool|null          $_validate @internal
     * @return bool|self
     * @throws froq\database\record\RecordException
     */
    public final function save(array &$data = null, array &$errors = null, array $options = null,
        string|array $drop = null, bool $bool = false, bool $select = false, bool $_validate = null): bool|self
    {
        [$table, $primary] = $this->pack();

        if ($data !== null) {
            $this->updateData($data);
        }

        $data ??= $this->getData() ?: $this->getFormData();
        if (empty($data)) {
            throw new RecordException(
                'No data given yet for save(), call setData() or load() '.
                'first or pass $data argument to save()'
            );
        }

        // When primary given id() or setId() before.
        if ($primary && isset($this->data[$primary])) {
            $data[$primary] = $this->data[$primary];
        }

        // Options are used for only save actions.
        $options = array_merge($this->options, $options ?? []);

        // Default is true (@see froq\database\trait\RecordTrait).
        $_validate ??= (bool) $options['validate'];

        // Run validation.
        if ($_validate && !$this->isValid($data, $errors, $options)) {
            throw new ValidationError(
                'Cannot save record (%s), validation failed [tip: run save() '.
                'in a try/catch block and use errors() to see error details]',
                static::class, errors: $errors
            );
        }

        // Detect insert/update.
        $new = !isset($primary) || !isset($data[$primary]);

        // Check id validity.
        if (!$new) {
            $id = $data[$primary] ?? null;
            $id || throw new RecordException('Empty primary value');
        } else {
            unset($data[$primary]);
        }

        // When no transaction wrap requested.
        if ($options['transaction']) {
            $data = $new ? $this->db->transaction(fn() => $this->doInsert($data, $table, $primary, $options))
                         : $this->db->transaction(fn() => $this->doUpdate($data, $table, $primary, $options, $id));
        } else {
            $data = $new ? $this->doInsert($data, $table, $primary, $options)
                         : $this->doUpdate($data, $table, $primary, $options, $id);
        }

        // When select whole/fresh data wanted (works with primary's only).
        if (isset($options['select']) || $select) {
            $select = $options['select'] ?? $select;
            $select && $data = (array) $this->db->select($table, where: [$primary => $data[$primary]]);
        }

        // Drop unwanted fields.
        if (isset($options['drop']) || $drop) {
            $fields = $options['drop'] ?? $drop;

            // Collect null field keys.
            if ($fields == '@null') {
                $fields = array_keys(array_filter($data, 'is_null'));
            }

            // Comma-separated list.
            $fields = is_string($fields) ? split('[, ]', $fields) : $fields;
            foreach ((array) $fields as $field) {
                unset($data[$field]);
            }
        }

        // Update data on both record & form.
        $this->setData($data);
        if ($form = $this->getForm()) {
            $form->setData($data);
        }

        // When bool return wanted.
        if (isset($options['bool']) || $bool) {
            $bool = $options['bool'] ?? $bool;
            if ($bool) {
                return $this->isSaved();
            }
        }

        return $this;
    }

    /**
     * Find and get a record from target table by given id or own id, set `$finded` property, throw a
     * `RecordException` if id is empty or cause a `RecordException` if no table primary presented.
     *
     * @param  int|string|null   $id
     * @param  array|string|null $cols
     * @return froq\database\record\Record
     * @throws froq\database\record\RecordException
     */
    public final function find(int|string $id = null, array|string $cols = null): Record
    {
        $id ??= $this->id();

        [$table, $primary, $id] = $this->pack($id, primary: true);

        $id || throw new RecordException('Empty primary value');

        $query = $this->query()->equal($primary, $id);
        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $cols = $cols ?: $this->query->pull('return', 'fields') ?: '*';
        $data = $query->select($cols)->from($table)
                      ->getArray();

        $this->finded = $data ? 1 : 0;

        $that = $this->copy($table, $primary);
        $that->finded = $this->finded;

        if ($data) {
            $this->setData($data);
            $that->setData($data);
        }

        return $that;
    }

    /**
     * Find and get all records from target table by given ids, set `$finded` property, throw a `RecordException`
     * if ids are empty or cause a `RecordException` if no table primary presented.
     *
     * @param  array<int|string>         $ids
     * @param  array|string|null         $cols
     * @param  froq\database\Pager|null &$pager
     * @param  int|null                  $limit
     * @return froq\database\record\RecordList
     * @throws froq\database\record\RecordException
     */
    public final function findAll(array $ids, array|string $cols = null, Pager &$pager = null, int $limit = null): RecordList
    {
        [$table, $primary, $ids] = $this->pack($ids, primary: true);

        $ids || throw new RecordException('Empty primary values');

        $query = $this->query()->equal($primary, [$ids]);
        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $cols = $cols ?: $this->query->pull('return', 'fields') ?: '*';
        $data = $query->select($cols)->from($table)
                      ->getArrayAll($pager, $limit);

        $this->finded = $data ? count($data) : 0;

        $that = $this->copy($table, $primary);
        $that->finded = $this->finded;

        $thats = [];
        if ($data) foreach ($data as $dat) {
            $thats[] = (clone $that)->setData((array) $dat);
        }

        return new RecordList($thats, $pager);
    }

    /**
     * Remove a record from target table by given id or own id, set `$removed` property, throw a `RecordException`
     * if id is empty or cause a `RecordException` if no table primary presented.
     *
     * @param  int|string|null $id
     * @return int|froq\database\record\Record|null
     * @throws froq\database\record\RecordException
     */
    public final function remove(int|string $id = null): int|Record|null
    {
        $id ??= $this->id();

        [$table, $primary, $id] = $this->pack($id, primary: true);

        $id || throw new RecordException('Empty primary value');

        $query = $this->query()->equal($primary, $id);

        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $return = $this->query->pull('return', 'fields');
        $return && $query->return($return, 'array');

        $query->delete()->from($table);

        $result = $query->run();

        // With a record.
        if ($return) {
            $this->removed = $data = $result->rows(0);

            $that = $this->copy($table, $primary);
            $that->removed = $this->removed;

            if ($data) {
                $this->setData($data);
                $that->setData($data);
            }

            return $that;
        }

        // With a count.
        $this->removed = $result->count();

        return $this->removed;
    }

    /**
     * Remove all records from target table by given ids, set `$removed` property, throw a `RecordException`
     * if ids is empty or cause a `RecordException` if no table primary presented.
     *
     * @param  array<int|string> $ids
     * @return int|froq\database\record\RecordList|null
     * @throws froq\database\record\RecordException
     */
    public final function removeAll(array $ids): int|RecordList|null
    {
        [$table, $primary, $ids] = $this->pack($ids, primary: true);

        $ids || throw new RecordException('Empty primary values');

        $query = $this->query()->equal($primary, [$ids]);
        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $return = $this->query->pull('return', 'fields');
        $return && $query->return($return, 'array');

        $query->delete()->from($table);

        $result = $query->run();

        // With a record list.
        if ($return) {
            $this->removed = $data = $result->rows();

            $that = $this->copy($table, $primary);
            $that->removed = $this->removed;

            $thats = [];
            if ($data) foreach ($data as $dat) {
                $thats[] = (clone $that)->setData((array) $dat);
            }

            return new RecordList($thats);
        }

        // With a count.
        $this->removed = $result->count();

        return $this->removed;
    }

    /**
     * Find a record by given id (and optionally given where), and save it with given new data if find successes. This
     * method shortcut for find() > save() process with a boolean return.
     *
     * @param  int|string         $id
     * @param  array|null        &$data
     * @param  array|null        &$errors
     * @param  array|null         $options
     * @param  string|array|null  $drop
     * @param  array|null         $where
     * @param  bool|null          $_validate @internal
     * @return bool
     */
    public final function findSave(int|string $id, array &$data = null, array &$errors = null, array $options = null,
        string|array $drop = null, array $where = null, bool $_validate = null): bool
    {
        // Will be used for only find().
        $where && $this->where($where);

        return $this->find($id)->isFinded()
            && $this->save($data, $errors, $options, $drop, _validate: $_validate)->isSaved();
    }

    /**
     * Find a record by given id (and optionally given where), and remove it. This method shortcut for find() > remove()
     * process with a boolean return.
     *
     * @param  int|string  $id
     * @param  array|null  $where
     * @return bool
     */
    public final function findRemove(int|string $id, array $where = null): bool
    {
        // Will be used for only find().
        $where && $this->where($where);

        return $this->find($id)->isFinded() && $this->remove();
    }

    /**
     * Find multiple records by given arguments returning a RecordList filled by found records.
     *
     * @param  string|array    $where
     * @param  any          ...$args For select() method.
     * @return froq\database\record\RecordList
     */
    public final function findBy(string|array $where, ...$args): RecordList
    {
        $rows = $this->select($where, ...$args);
        $that = $this->copy();

        // For single records.
        if (isset($args['limit'])) {
            $rows = [(array) $rows];
        }

        $thats = [];
        if ($rows) foreach ($rows as $row) {
            $thats[] = (clone $that)->setData((array) $row);
        }

        return new RecordList($thats);
    }

    /**
     * Remove multiple records by given arguments returning a RecordList filled by removed records.
     *
     * @param  string|array    $where
     * @param  any          ...$args For delete() method.
     * @return froq\database\record\RecordList
     */
    public final function removeBy(string|array $where, ...$args): RecordList
    {
        // For returning fields.
        if (!isset($args['return'])) {
            $args['return'] = '*';
        }

        $rows = $this->delete($where, ...$args);
        $that = $this->copy();

        $thats = [];
        if ($rows) foreach ($rows as $row) {
            $thats[] = (clone $that)->setData((array) $row);
        }

        return new RecordList($thats);
    }

    /**
     * Select record(s) from own table by given conditions.
     *
     * @param  string|array      $where
     * @param  array|null        $params
     * @param  string|null       $op
     * @param  string            $fields
     * @param  string|null       $order
     * @param  string|array|null $fetch
     * @return array|object|null
     */
    public final function select(string|array $where, array $params = null, string $op = null, int|null $limit = 1,
        string $fields = '*', string $order = null, string|array $fetch = null): array|object|null
    {
        $query = $this->query()->select($fields);
        $query->where($where, $params, $op);

        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $limit && $query->limit($limit);
        $order && $query->order($order);

        $result = $query->run($fetch);

        return ($limit == 1) ? $result->rows(0) : $result->rows();
    }

    /**
     * Update record(s) on own table by given conditions.
     *
     * @param  array        $data
     * @param  string|array $where
     * @param  array|null   $params
     * @param  string|null  $op
     * @return int|null
     */
    public final function update(array $data, string|array $where, array $params = null, string $op = null): int|null
    {
        $query = $this->query()->update($data);
        $query->where($where, $params, $op);

        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        return $query->run()->count();
    }

    /**
     * Delete record(s) from own table by given conditions.
     *
     * @param  string|array where
     * @param  array|null   $params
     * @param  string|null  $op
     * @return int|null
     */
    public final function delete(string|array $where, array $params = null, string $return = '', string $op = null): int|array|null
    {
        $query = $this->query()->delete();
        $query->where($where, $params, $op);

        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        // Returning fields.
        $return && $query->return($return);

        $result = $query->run();

        return $return ? $result->rows() : $result->count();
    }

    /**
     * Pack table, table primary and id/ids stuff, throw a `RecordException` if no table presented or no table
     * primary presented when primary check requested as `$primary = true`.
     *
     * @param  int|string|array<int|string>|null $id
     * @param  bool                              $primary
     * @return array
     * @throws froq\database\record\RecordException
     */
    private function pack(int|string|array $id = null, bool $primary = false): array
    {
        if (empty($this->table)) {
            throw new RecordException(
                'No $table property defined on %s class, call setTable()',
                static::class
            );
        }
        if ($primary && empty($this->tablePrimary)) {
            throw new RecordException(
                'No $tablePrimary property defined on %s class, call setTablePrimary()',
                static::class
            );
        }

        return [$this->table, $this->tablePrimary ?? null, $id];
    }

    /**
     * Create a static copy instance with some own basic properties.
     *
     * @param  string|null $table
     * @param  string|null $primary
     * @return static
     */
    private function copy(string $table = null, string $primary = null): static
    {
        if (static::class == self::class) {
            $that = new self($this->db);
        } else {
            $that = new static();
            $that->db = $this->db;
        }

        $that->setTable($table ?? $this->getTable())
             ->setTablePrimary($primary ?? $this->getTablePrimary());

        return $that;
    }

    /**
     * Do an insert action.
     *
     * @param  array       $data
     * @param  string      $table
     * @param  string|null $primary
     * @param  array       $options
     * @return array
     */
    private function doInsert(array $data, string $table, string|null $primary, array $options): array
    {
        $return   = $options['return']   ?? null;     // Whether returning any field(s) or current data.
        $sequence = $options['sequence'] ?? $primary; // Whether table has sequence or not.

        $query    = $this->query()->insert($data, sequence: !!$sequence);

        $return ??= $this->query->pull('return', 'fields');
        $return   && $query->return($return, 'array');

        $conflict = $this->query->pull('conflict');
        $conflict && $query->conflict(...$conflict);

        $result   = $query->run();

        unset($query);

        // Get new id if available.
        $id = $result->id();

        $this->saved = (bool) $result->count();

        // Swap data with returning data.
        if ($return) {
            $result = (array) $result->first();
            if (isset($result[$return])) {
                $result = [$return => $result[$return]];
            }
            $data = $result;
        }

        if ($primary && $id) {
            // Put on the top primary.
            $data = [$primary => $id] + $data;

            // Set primary value with new id.
            $this->id($id);
        }

        return $data;
    }

    /**
     * Do an insert action.
     *
     * @param  array       $data
     * @param  string      $table
     * @param  string|null $primary
     * @param  array       $options
     * @param  int|string  $id
     * @return array
     */
    private function doUpdate(array $data, string $table, string|null $primary, array $options, int|string $id): array
    {
        $return = $options['return'] ?? null; // Whether returning any field(s) or current data.

        unset($data[$primary]); // Not needed in data set.

        $query    = $this->query()->update($data)->equal($primary, $id);

        $return ??= $this->query->pull('return', 'fields');
        $return   && $query->return($return, 'array');

        $conflict = $this->query->pull('conflict');
        $conflict && $query->conflict(...$conflict);

        $where    = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $result = $query->run();

        unset($query);

        // Set primary value with given id.
        $this->id($id);

        $this->saved = (bool) $result->count();

        // Swap data with returning data.
        if ($return) {
            $result = (array) $result->first();
            if (isset($result[$return])) {
                $result = [$return => $result[$return]];
            }
            $data = $result;
        }

        // Put on the top primary.
        $data = [$primary => $id] + $data;

        return $data;
    }
}
