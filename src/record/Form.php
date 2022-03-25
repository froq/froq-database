<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\database\{Database, trait\RecordTrait};
use froq\validation\ValidationError;

/**
 * Form.
 *
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

    /** @var bool */
    private bool $saved;

    /**
     * Constructor.
     *
     * @param  froq\database\Database|null      $db
     * @param  string|null                      $table
     * @param  string|null                      $tablePrimary
     * @param  array|null                       $data
     * @param  froq\database\record\Record|null $record
     * @param  array|null                       $options
     * @param  array|null                       $validations
     * @param  array|null                       $validationRules
     * @param  array|null                       $validationOptions
     * @param  string|null                      $name
     * @throws froq\database\record\FormException
     */
    public function __construct(Database $db = null, string $table = null, string $tablePrimary = null,
        array $data = null, string|Record $record = null, array $options = null, array $validations = null,
        array $validationRules = null, array $validationOptions = null, string $name = null)
    {
        // Try to use active app database object.
        $db ??= function_exists('app') ? app()->database() : throw new FormException(
            'No database given to deal, be sure running this module with `froq\app` ' .
            'module and be sure `database` option exists in app config or pass $db argument'
        );

        $this->db = $db;

        $data && $this->data = $data;
        $name && $this->name = $name;

        // Both record class & instance accepted.
        if ($record) {
            if ($record instanceof Record) {
                $this->record      = $record;
                $this->recordClass = $record::class;
            } else {
                $this->recordClass = $record;
            }
        }

        $this->setOptions($options, self::$optionsDefault);

        // Validations can be combined or simple array'ed.
        if ($validations) {
            isset($validations['@rules'])   && $validationRules   = array_pull($validations, '@rules');
            isset($validations['@options']) && $validationOptions = array_pull($validations, '@options');

            // Simple array'ed if no "@rules" field given.
            $validationRules ??= $validations;
        }

        // Set table & validation stuff.
        $table             && $this->table             = $table;
        $tablePrimary      && $this->tablePrimary      = $tablePrimary;
        $validationRules   && $this->validationRules   = $validationRules;
        $validationOptions && $this->validationOptions = $validationOptions;
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
     * Get a record instance setting and returning own or creating new one from provided record class or
     * default.
     *
     * @return froq\database\record\Record
     */
    public final function getRecordInstance(): Record
    {
        // Use internal or own (current) record/record class if available.
        $record = $this->record ?? $this->recordClass ?? new Record(
            $this->db, $this->getTable(), $this->getTablePrimary(),
            data: $this->getData(), form: $this, options: $this->options,
            validationRules: $this->getValidationRules(), validationOptions: $this->getValidationOptions()
        );

        // If class given.
        if (is_string($record)) {
            // Check also class validity.
            $this->setRecordClass($record);

            // Init & update own record.
            $this->setRecord($record = new $record());
        }

        return $record;
    }

    /**
     * Aliases.
     */
    public final function okay(...$args)  { return $this->isValid(...$args); }

    /**
     * Check whether given or own data is valid filtering/sanitizing data, fill `$errors` argument with
     * validation errors if validation fails, throw a `FormException` if given or own record data or own
     * rules is empty.
     *
     * @param  array|null &$data
     * @param  array|null &$errors
     * @param  array|null  $options
     * @return bool
     * @throws froq\database\record\FormException
     */
    public final function isValid(array &$data = null, array &$errors = null, array $options = null): bool
    {
        $data ??= $this->getData() ?: $this->getRecordData();
        if (empty($data)) {
            throw new FormException(
                'No data set yet for validation, call setData() or load() '.
                'first or pass $data argument to isValid()'
            );
        }

        $rules     = $this->getValidationRules() ?: $this->getRecord()?->getValidationRules();
        $options ??= $this->getValidationOptions() ?: $this->getRecord()?->getValidationOptions();

        if (empty($rules)) {
            throw new FormException(
                'No validation rules set yet, call setValidationRules() '.
                'or define $validationRules property on %s class',
                static::class
            );
        }

        $this->runValidation($data, $rules, $options, $errors);

        // Update with modified stuff (returned by refs).
        $this->data = $data;

        // Update record too.
        if ($record = $this->getRecord()) {
            $record->setData($data);
            if ($errors !== null) {
                $record->setValidationErrors($errors);
            }
        }

        return $this->validated;
    }

    /**
     * Proxy method to own record saved state/result.
     *
     * @param  int|string|null &$id
     * @return bool
     */
    public final function isSaved(int|string &$id = null): bool
    {
        $this->getRecord()?->isSaved($id);

        return !!$id;
    }

    /**
     * Check whether form is sent (submitted) with given or own name, or request method is post.
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
     * Save own data via a newly created record entity returning that record, throw a `FormException` if no
     * validation was run or throw a `ValidationError` if runned validation was failed.
     *
     * @param  array|null $options
     * @return froq\database\record\Record
     * @throws froq\database\record\FormException
     */
    public final function save(array $options = null): Record
    {
        if ($this->validated === null) {
            throw new FormException('Cannot run save process, form not validated yet');
        } elseif ($this->validated === false) {
            throw new ValidationError('Cannot run save process, form validation was failed [tip: run save()'
                . ' in a try/catch block and use errors() to see error details]', errors: $this->errors());
        }

        $this->record = $this->getRecordInstance();

        if (empty($this->table) && empty($table = $this->record->getTable())) {
            throw new FormException('No table set yet, call setTable() first or define $table property on %s'
                . ' class to run save()', static::class);
        }
        if (empty($this->data)) {
            throw new FormException('No data set yet for save(), call setData() or isValid($data) first');
        }

        // Options are used for only save actions.
        $options = array_merge($this->options, $options ?? []);

        $this->record->save($this->data, options: $options, _validate: false /* Must be validated until here.. */)
                     ->setForm($this);

        // Require new validation.
        $this->validated = null;

        return $this->record;
    }
}
