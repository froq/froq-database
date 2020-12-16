<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\database\record\{FormException, Record};
use froq\database\{Database, trait\DbTrait, trait\TableTrait, trait\ValidationTrait};
use froq\common\traits\{DataTrait, DataLoadTrait};
use froq\common\interfaces\{Arrayable, Sizable};
use froq\validation\ValidationException;

/**
 * Form.
 *
 * Represents a form entity that aims to run validation processes via its validation rules, also is available to
 * save those validated data via a record entity.
 *
 * @package froq\database\record
 * @object  froq\database\record\Form
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   5.0
 */
class Form implements Arrayable, Sizable
{
    /**
     * @see froq\database\trait\DbTrait
     * @see froq\database\trait\TableTrait
     * @see froq\database\trait\ValidationTrait
     * @see froq\common\traits\DataTrait
     * @see froq\common\traits\DataLoadTrait
     */
    use DbTrait, TableTrait, ValidationTrait, DataTrait, DataLoadTrait;

    /** @var string */
    protected string $name;

    /** @var froq\database\record\Record */
    protected Record $record;

    /** @var string */
    protected string $recordClass;

    /** @var int|string|bool */
    private int|string|bool $saved;

    /**
     * Constructor.
     *
     * @param  froq\database\Database|null $db
     * @param  string|null                 $table
     * @param  string|null                 $tablePrimary
     * @param  array|null                  $data
     * @param  string|null                 $name
     * @param  array|null                  $validationRules
     * @param  array|null                  $validationOptions
     * @throws froq\database\record\FormException
     */
    public function __construct(Database $db = null, string $table = null, string $tablePrimary = null, array $data = null,
        string|Record $record = null, string $name = null, array $validationRules = null, array $validationOptions = null)
    {
        // Try to use active app database object.
        $db = (!$db && function_exists('app')) ?  app()->database() : $db;

        if ($db == null) {
            throw new FormException('No database given to deal, be sure running this module with froq\app'
                . ' module and be sure `database` option exists in app config or pass $db argument');
        }

        $this->db = $db;

        $data && $this->data = $data;
        $name && $this->name = $name;

        if ($record != null) {
            if ($record instanceof Record) {
                $this->record = $record;
                $this->recordClass = $record::class;
            } else {
                $this->recordClass = $record;
            }
        }

        // Set table stuff & validation stuff.
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
        $this->record = $record;
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
     * Get a record instance setting and returning owned or creating new one, throw a `FormException` if no `$record`
     * and `$recordClass` was defined on extender class.
     *
     * @return froq\database\record\Record|null
     * @throws froq\database\record\FormException
     */
    public final function getRecordInstance(): Record|null
    {
        // Use internal or owned (current) record/record class if available.
        $record = $this->record ?? $this->recordClass ?? new Record(
            $this->db, $this->getTable(), $this->getTablePrimary(), $this->getData(), $this,
            validationRules: $this->getValidationRules(), validationOptions: $this->getValidationOptions()
        );

        // If class given.
        if (is_string($record)) {
            // Check also class validity.
            $this->setRecordClass($record);

            // Init & update owned record.
            $this->setRecord($record = new $record());
        }

        return $record;
    }

    /**
     * @alias of isValid().
     */
    public final function okay(...$args)
    {
        return $this->isValid(...$args);
    }

    /**
     * Check whether given or owned data is valid filtering/sanitizing data, fill `$errors` argument with
     * validation errors if validation fails, throw a `FormException` if given or owned record data or owned
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
        $data ??= $this->getData() ?? $this->getRecordData();
        if (empty($data)) {
            throw new FormException('No data set yet for validation, call setData() first or pass'
                . ' $data argument to isValid()');
        }

        $rules = $this->getValidationRules() ?? $this->getRecord()?->getValidationRules();
        $options ??= $this->getValidationOptions() ?? $this->getRecord()?->getValidationOptions();

        if (empty($rules)) {
            throw new FormException('No validation rules set yet, call setValidationRules() or define'
                . ' $validationRules property on %s class', static::class);
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
     * Proxy method to owned record saved state/result.
     *
     * @param  int|string|bool|null &$saved
     * @return bool
     */
    public final function isSaved(int|string|bool &$saved = null): bool
    {
        $this->getRecord()?->isSaved($saved);

        return !!$saved;
    }

    /**
     * Check whether form is sent (submitted) with given or owned name, or request method is post.
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
     * Save owned data via a newly created record entity returning that record, throw a `FormException` if no
     * validation was run or throw a `ValidationException` if runned validation was failed.
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
            throw new ValidationException('Cannot run save process, form validation was failed [tip: run save()'
                . ' in a try/catch block and use errors() to see error details]', errors: $this->errors());
        }

        $record = $this->getRecordInstance();

        if (empty($this->table) && empty($table = $record->getTable())) {
            throw new FormException('No table set yet, call setTable() first or define $table property on %s'
                . ' class to run save()', static::class);
        }
        if (empty($this->data)) {
            throw new FormException('No data set yet for save(), call setData() or isValid($data) first');
        }

        // Require new validation.
        $this->validated = null;

        ($this->record = $record)
            ->save($this->data, $options, validate: false /* 'cos must be validated until here */)
            ->setForm($this);

        return $this->record;
    }
}
