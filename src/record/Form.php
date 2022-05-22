<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\database\{Database, DatabaseRegistry, DatabaseRegistryException};
use froq\database\{common\Table, trait\RecordTrait};
use froq\validation\ValidationError;

/**
 * A class, aims to run validation processes via its validation rules, also is able
 * to save the validated data via a `$record` property.
 *
 * @package froq\database\record
 * @object  froq\database\record\Form
 * @author  Kerem Güneş
 * @since   5.0
 */
class Form implements FormInterface
{
    use RecordTrait;

    /** @var froq\database\record\Record */
    protected Record $record;

    /** @var string */
    protected string $recordClass;

    /** @var string */
    protected string $name;

    /**
     * Constructor.
     *
     * @param  froq\database\Database|null             $db
     * @param  string|froq\database\common\Table|null  $table
     * @param  string|froq\database\record\Record|null $record
     * @param  array|null                              $data
     * @param  array|null                              $options
     * @param  array|null                              $validations
     * @param  string|null                             $name
     */
    public function __construct(Database $db = null, string|Table $table = null, string|Record $record = null,
        array $data = null, array $options = null, array $validations = null, string $name = null)
    {
        // Try to use active database when none given.
        $this->db = $db ?? DatabaseRegistry::getDefault(__method__);

        $data && $this->data = $data;
        $name && $this->name = $name;

        if ($table) {
            if ($table instanceof Table) {
                $this->table = $table;
            } else {
                $this->table = new Table($table);
            }
        }

        if ($record) {
            if ($record instanceof Record) {
                $this->record      = $record;
                $this->recordClass = $record::class;
            } else {
                $this->recordClass = $record;
            }
        }

        $this->setOptions($options)->setValidations($validations);
    }

    /**
     * Set name property.
     *
     * @param  string $name
     * @return self
     */
    public final function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name property.
     *
     * @return string|null
     */
    public final function getName(): string|null
    {
        return $this->name ?? null;
    }

    /**
     * Set record property updating record-class property and record's form.
     *
     * @param  froq\database\record\Record $record
     * @return self
     */
    public final function setRecord(Record $record): self
    {
        $this->record      = $record;
        $this->recordClass = $record::class;

        // Prevent recursion, 'cos setForm() calls setRecord() back.
        if ($this->record->getForm() !== $this) {
            $this->record->setForm($this);
        }

        return $this;
    }

    /**
     * Get record property.
     *
     * @return froq\database\record\Record|null
     */
    public final function getRecord(): Record|null
    {
        return $this->record ?? null;
    }

    /**
     * Set record-class property.
     *
     * @param  string $recordClass
     * @return self
     * @throws froq\database\record\FormException
     */
    public final function setRecordClass(string $recordClass): self
    {
        if (!class_exists($recordClass)) {
            throw new FormException('Given record class `%s` not exists', $recordClass);
        }
        if (!class_extends($recordClass, Record::class)) {
            throw new FormException('Given record class `%s` must extend class `%s`',
                [$recordClass, Record::class]);
        }

        $this->recordClass = $recordClass;

        return $this;
    }

    /**
     * Get record-class property.
     *
     * @return string|null
     */
    public final function getRecordClass(): string|null
    {
        return $this->recordClass ?? null;
    }

    /**
     * Get record's data property if record property exists.
     *
     * @return array|null
     */
    public final function getRecordData(): array|null
    {
        return $this->getRecord()?->getData();
    }

    /**
     * Get a record instance setting and returning self or creating new one from
     * provided record class or default.
     *
     * @return froq\database\record\Record
     */
    public final function getRecordInstance(): Record
    {
        // Use internal or self record/record class if available.
        $record = $this->record ?? $this->recordClass ?? new Record(
            db: $this->db, form: $this,
            data: $this->getData(), table: $this->getTable(),
            options: $this->getOptions(), validations: $this->getValidations()
        );

        if (is_string($record)) {
            // Checks also class validity.
            $this->setRecordClass($record);

            // Init & update record.
            $this->setRecord($record = new $record());
        }

        return $record;
    }

    /** Aliases. */
    public final function okay(&...$args)  { return $this->isValid(...$args); }

    /**
     * Check whether given or self data is valid filtering/sanitizing data, fill `$errors`
     * argument with validation errors if validation fails.
     *
     * @param  array|null &$data
     * @param  array|null &$errors
     * @param  array|null  $options
     * @return bool
     */
    public final function isValid(array &$data = null, array &$errors = null, array $options = null): bool
    {
        $data    ??= $this->getData() ?: $this->getRecordData();
        $rules     = $this->validation->getRules() ?: $this->getRecord()?->getValidation()->getRules();
        $options ??= $this->validation->getOptions() ?: $this->getRecord()?->getValidation()->getOptions();

        $this->validation->run($data, $errors, $rules, $options);

        // Update with modified stuff (byref).
        $this->setData($data);

        // Update record too.
        if ($record = $this->getRecord()) {
            $record->setData($data);
        }

        return $this->validation->result();
    }

    /**
     * Proxy method to self record saved state.
     *
     * @param  int|string|null &$id
     * @return bool
     */
    public final function isSaved(int|string &$id = null): bool
    {
        return (bool) $this->getRecord()?->isSaved($id);
    }

    /**
     * Check whether form is sent (submitted) with given or self name, or request
     * method is post.
     *
     * @param  string|null $name
     * @return bool
     */
    public final function isSent(string $name = null): bool
    {
        $name ??= $this->getName();

        if ($name !== null) {
            return isset($_POST[$name]);
        }

        return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') == 'POST';
    }

    /**
     * Save given or self data via a newly created record entity returning that record,
     * throw a `FormException` if no validation was run or throw a `ValidationError` if
     * validation was failed.
     *
     * @param  array|null &$data
     * @param  array|null  $options
     * @return froq\database\record\Record
     * @throws froq\database\record\FormException
     */
    public final function save(array &$data = null, array $options = null): Record
    {
        // Run validation when data given directly.
        if ($data !== null) {
            $this->isValid($data, options: $options);
        }

        $data ??= $this->getData() ?: $this->getRecordData();
        $data || throw new FormException(
            'No data yet, call setData() or pass $data argument to save()'
        );

        $result = $this->validation->result();

        if ($result === null) {
            throw new FormException(
                'Cannot run save process, form not validated yet, call isValid()'
            );
        }
        if ($result === false) {
            throw new ValidationError(
                'Cannot run save process, form validation was failed [tip: %s]',
                ValidationError::tip(), errors: $this->validation->errors()
            );
        }

        // Options are used for only save actions.
        $options = [...$this->options, ...$options ?? []];

        $this->record = $this->getRecordInstance()
              ->save($data, options: $options, validate: false /* Must be validated until here.. */)
              ->setForm($this);

        // Require new validation.
        $this->validation->reset();

        return $this->record;
    }
}
