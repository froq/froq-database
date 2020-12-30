<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\database\record\{RecordException, Form, FormException};
use froq\database\{Database, Query, trait\RecordTrait};
use froq\common\trait\{DataTrait, DataLoadTrait, DataMagicTrait};
use froq\common\interface\{Arrayable, Sizable};
use froq\validation\ValidationException;
use froq\pager\Pager;

/**
 * Record.
 *
 * Represents a record entity that mimics ActiveRecord pattern and may be extended by many record classes
 * to handle CRUD operations in a safe way via form validation.
 *
 * @package froq\database\record
 * @object  froq\database\record\Record
 * @author  Kerem Güneş
 * @since   5.0
 */
class Record implements Arrayable, Sizable
{
    /**
     * @see froq\database\trait\RecordTrait
     * @see froq\common\trait\DataTrait
     * @see froq\common\trait\DataLoadTrait
     * @see froq\common\trait\DataMagicTrait
     */
    use RecordTrait, DataTrait, DataLoadTrait, DataMagicTrait;

    /** @var froq\database\record\Form */
    protected Form $form;

    /** @var string */
    protected string $formClass;

    /** @var froq\database\Query */
    protected Query $query;

    /** @var int|string */
    private int|string $id;

    /** @var bool */
    private bool $saved;

    /** @var int, int */
    private int $finded, $removed;

    /**
     * Constructor.
     *
     * @param  froq\database\Database|null           $db
     * @param  string|null                           $table
     * @param  string|null                           $tablePrimary
     * @param  array|null                            $data
     * @param  string|froq\database\record\Form|null $form
     * @param  array|null                            $validationRules
     * @param  array|null                            $validationOptions
     * @throws froq\database\record\FormException
     */
    public function __construct(Database $db = null, string $table = null, string $tablePrimary = null,
        array $data = null, string|Form $form = null, array $options = null, array $validationRules = null,
        array $validationOptions = null)
    {
        // Try to use active app database object.
        $db ??= function_exists('app') ? app()->database() : null;

        if ($db == null) {
            throw new RecordException('No database given to deal, be sure running this module with froq\app'
                . ' module and be sure `database` option exists in app config or pass $db argument');
        }

        $this->db    = $db;
        $this->query = new Query($db, table: $table);

        $data && $this->data = $data;

        if ($form != null) {
            if ($form instanceof Form) {
                $this->form = $form;
                $this->formClass = $form::class;
            } else {
                $this->formClass = $form;
            }
        }

        $this->setOptions($options, self::$optionsDefault);

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
     * Get a form instance setting and returning owned or creating new one from provided form class or
     * default.
     *
     * @return froq\database\record\Form
     */
    public final function getFormInstance(): Form
    {
        // Use internal or owned (current) form/form class if available.
        $form = $this->form ?? $this->formClass ?? new Form(
            $this->db, $this->getTable(), $this->getTablePrimary(),
            data: $this->getData(), record: $this, options: $this->options,
            validationRules: $this->getValidationRules(), validationOptions: $this->getValidationOptions()
        );

        // If class given.
        if (is_string($form)) {
            // Check also class validity.
            $this->setFormClass($form);

            // Init & update owned form.
            $this->setForm($form = new $form());
        }

        return $form;
    }

    /**
     * Set id.
     *
     * @param  int|string $id
     * @return self
     */
    public final function setId(int|string $id): self
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

    /**
     * @alias of isFinded()
     */
    public final function found(...$args)
    {
        return $this->isFinded(...$args);
    }

    /**
     * Proxy method to owned form class for validation processes.
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

        return !empty($this->saved);
    }

    /**
     * Check finded state/result, fill given state argument.
     *
     * @param  int|null &$finded
     * @return bool
     */
    public final function isFinded(int &$finded = null): bool
    {
        $finded = $this->finded ?? null;

        return !!$finded;
    }

    /**
     * Check removed state/result, fill given state argument.
     *
     * @param  int|null &$removed
     * @return bool
     */
    public final function isRemoved(int &$removed = null): bool
    {
        $removed = $this->removed ?? null;

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
     * Set/get id property and id (primary) field of data stack, cause a `RecordException` if no table primary
     * presented yet.
     *
     * @param  int|string|null $id
     * @return int|string|self|null
     */
    public final function id(int|string $id = null): int|string|self|null
    {
        [, $primary] = $this->pack(primary: true);

        if (func_num_args()) {
            $this->__set($primary, ($this->id = $id));

            return $this;
        }

        return $this->id ?? $this->__get($primary);
    }

    /**
     * Save given or owned data to target table, set `$saved` property, set `$id` property if table primary was
     * presented, throw a `RecordException` if no data or target table given yet or throw a `ValidationException`
     * if validation fails.
     *
     * @param  array|null $data
     * @param  array|null $options
     * @param  array|null &$errors
     * @param  bool       $validate @internal
     * @return self
     * @throws froq\database\record\RecordException
     */
    public final function save(array &$data = null, array &$errors = null, array $options = null, bool $validate = true): self
    {
        [$table, $primary] = $this->pack();

        if ($data !== null) {
            $this->setData($data);
        }

        $data ??= $this->getData() ?? $this->getFormData();
        if ($data == null) {
            throw new RecordException('No data given yet for save(), call setData() or load() first or '
                . ' try calling save($data)');
        }

        // Run validation.
        if ($validate) {
            ($this->validated = $this->isValid($data, $errors, $options))
                || throw new ValidationException('Cannot save record, validation failed [tip: run save()'
                    . ' in a try/catch block and use errors() to see error details]', errors: $errors);
        }

        // Options are used for only save actions.
        $options = array_merge($this->options, $options ?? []);

        // Detect insert/update.
        $new = !isset($primary) || !isset($data[$primary]);

        // Check id validity.
        if (!$new) {
            $id = $data[$primary] ?? null;
            $id || throw new RecordException('Empty primary value given for save()');
        }

        // When no transaction wrap requested.
        if ($options['transaction']) {
            $data = $new ? $this->db->transaction(fn() => $this->doInsert($data, $table, $primary, $options))
                         : $this->db->transaction(fn() => $this->doUpdate($data, $table, $primary, $options, $id));
        } else {
            $data = $new ? $this->doInsert($data, $table, $primary, $options)
                         : $this->doUpdate($data, $table, $primary, $options, $id);
        }

        // Update data on both record & form.
        $this->setData($data);
        if ($form = $this->getForm()) {
            $form->setData($data);
        }

        return $this;
    }

    /**
     * Find and get a record from target table by given id or owned id, set `$finded` property, throw a
     * `RecordException` if id is empty or cause a `RecordException` if no table primary presented.
     *
     * @param  int|string|null $id
     * @param  array|null      $cols
     * @return froq\database\record\Record
     * @throws froq\database\record\RecordException
     */
    public final function find(int|string $id = null, array $cols = null): Record
    {
        $id ??= $this->id();

        [$table, $primary, $id] = $this->pack($id, primary: true);

        if ($id == null) {
            throw new RecordException('Empty primary value given for find()');
        }

        $query = $this->query()->equal($primary, $id);
        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $cols = $cols ?: '*';
        $data = $query->select($cols)->from($table)
                      ->getArray();

        $this->finded = $data ? 1 : 0;

        // Prevent wrong argument errors on constructor.
        $that = (static::class == self::class)
              ? new static($this->db, $table, $primary)
              : new static();

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
     * @param  array|null                $cols
     * @param  froq\database\Pager|null &$pager
     * @param  int|null                  $limit
     * @return froq\database\record\Records
     * @throws froq\database\record\RecordException
     */
    public final function findAll(array $ids, array $cols = null, Pager &$pager = null, int $limit = null): Records
    {
        [$table, $primary, $ids] = $this->pack($ids, primary: true);

        if ($ids == null) {
            throw new RecordException('Empty primary values given for findAll()');
        }

        $query = $this->query()->equal($primary, [$ids]);
        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $cols = $cols ?: '*';
        $data = $query->select($cols)->from($table)
                      ->getArrayAll($pager, $limit);

        $this->finded = $data ? count($data) : 0;

        // Prevent wrong argument errors on constructor.
        $that = (static::class == self::class)
              ? new static($this->db, $table, $primary)
              : new static();

        $thats = [];
        if ($data) foreach ($data as $dat) {
            $thats[] = (clone $that)->setData((array) $dat);
        }

        return new Records($thats, $pager);
    }

    /**
     * Remove a record from target table by given id or owned id, set `$removed` property, throw a `RecordException`
     * if id is empty or cause a `RecordException` if no table primary presented.
     *
     * @param  int|string|null $id
     * @return int
     * @throws froq\database\record\RecordException
     */
    public final function remove(int|string $id = null): int
    {
        $id ??= $this->id();

        [$table, $primary, $id] = $this->pack($id, primary: true);

        if ($id == null) {
            throw new RecordException('Empty primary value given for remove()');
        }

        $query = $this->query()->equal($primary, $id);
        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $query->delete()->from($table);

        // Run & get affected rows count.
        $this->removed = (int) $query->runExec();

        return $this->removed;
    }

    /**
     * Remove all records from target table by given ids, set `$removed` property, throw a `RecordException`
     * if ids is empty or cause a `RecordException` if no table primary presented.
     *
     * @param  array<int|string> $ids
     * @return int
     * @throws froq\database\record\RecordException
     */
    public final function removeAll(array $ids): int
    {
        [$table, $primary, $ids] = $this->pack($ids, primary: true);

        if ($ids == null) {
            throw new RecordException('Empty primary values given for removeAll()');
        }

        $query = $this->query()->equal($primary, [$ids]);
        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $query->delete()->from($table);

        // Run & get affected rows count.
        $this->removed = (int) $query->runExec();

        return $this->removed;
    }

    /**
     * Pack table, table primary and id/ids stuff, throw a `RecordException` if no table presented or no table
     * primary presented when primary check requested as `$primary = true`.
     *
     * @param  int|string|array<int|string>|null $id
     * @param  bool                              $primary
     * @return array
     * @throws froq\database\record\RecordException
     * @internal
     */
    private function pack(int|string|array $id = null, bool $primary = false): array
    {
        if (empty($this->table)) {
            throw new RecordException('No $table property defined on %s class, call setTable()',
                static::class);
        }
        if ($primary && empty($this->tablePrimary)) {
            throw new RecordException('No $tablePrimary property defined on %s class, call setTablePrimary()',
                static::class);
        }

        return [$this->table, $this->tablePrimary ?? null, $id];
    }

    /**
     * Do an insert action.
     *
     * @param  array       $data
     * @param  string      $table
     * @param  string|null $primary
     * @param  array       $options
     * @return array
     * @internal
     */
    private function doInsert(array $data, string $table, string|null $primary, array $options): array
    {
        $return   = $options['return']   ?? null;     // Whether returning any field(s) or current data.
        $sequence = $options['sequence'] ?? $primary; // Whether table has sequence or not.

        $query    = $this->query()->insert($data, sequence: !!$sequence);
        $return   && $query->return($return, 'array');
        $result   = $query->run();

        unset($query);

        // Get new id if available.
        $id = $result->id();

        $this->saved = !!$result->count();

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
     * @internal
     */
    private function doUpdate(array $data, string $table, string|null $primary, array $options, int|string $id): array
    {
        $return = $options['return'] ?? null; // Whether returning any field(s) or current data.

        unset($data[$primary]); // Not needed in data set.

        $query  = $this->query()->update($data)->equal($primary, $id);
        $return && $query->return($return, 'array');

        $where = $this->query->pull('where');
        if ($where) foreach ($where as [$where, $op]) {
            $query->where($where, op: $op);
        }

        $result = $query->run();

        unset($query);

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

        // Set primary value with given id.
        $this->id($id);

        $this->saved = !!$result->count();

        return $data;
    }
}
