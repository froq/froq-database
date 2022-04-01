<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\trait;

use froq\common\Exception;
use froq\validation\Validation;

/**
 * A trait, provides validation properties and related methods with its state and errors.
 *
 * @package froq\database\trait
 * @object  froq\database\trait\ValidationTrait
 * @author  Kerem Güneş
 * @since   5.0
 */
trait ValidationTrait
{
    /** @var array, array */
    protected array $validationRules, $validationOptions;

    /** @var ?array */
    protected ?array $validationErrors = null;

    /** @var ?bool */
    protected ?bool $validated = null;

    /**
     * Get validated state.
     *
     * @return bool|null
     */
    public final function validated(): bool|null
    {
        return $this->validated;
    }

    /**
     * Get errors, alias of getValidationErrors().
     *
     * @return array|null
     */
    public final function errors(): array|null
    {
        return $this->validationErrors;
    }

    /**
     * Set validation rules.
     *
     * @param  array $validationRules
     * @return self
     */
    public final function setValidationRules(array $validationRules): self
    {
        $this->validationRules = $validationRules;

        return $this;
    }

    /**
     * Get validation rules.
     *
     * @return array|null
     */
    public final function getValidationRules(): array|null
    {
        return $this->validationRules ?? null;
    }

    /**
     * Set validation options.
     *
     * @param  array $validationOptions
     * @return self
     */
    public final function setValidationOptions(array $validationOptions): self
    {
        $this->validationOptions = $validationOptions;

        return $this;
    }

    /**
     * Get validation options.
     *
     * @return array|null
     */
    public final function getValidationOptions(): array|null
    {
        return $this->validationOptions ?? null;
    }

    /**
     * Set validation errors.
     *
     * @param  array $validationErrors
     * @return self
     */
    public final function setValidationErrors(array $validationErrors): self
    {
        $this->validationErrors = $validationErrors;

        return $this;
    }

    /**
     * Get validation errors.
     *
     * @return array|null
     */
    public final function getValidationErrors(): array|null
    {
        return $this->validationErrors;
    }

    /**
     * Load validations from given or default config file.
     *
     * @param  string|null $file
     * @return array
     * @throws froq\common\Exception
     */
    public static final function loadValidations(string $file = null): array
    {
        // Try to load default file.
        $file ??= APP_DIR . '/app/config/validations.php';

        is_file($file) || throw new Exception(
            'No validations file exists such `%s`', $file
        );

        $validations = include $file;
        is_array($validations) || throw new Exception(
            'Validation files must return an array, %t returned', $validations
        );

        return $validations;
    }

    /**
     * Load validation rules from given or default config file, with given key.
     *
     * @param  string      $key
     * @param  string|null $file
     * @return array
     * @throws froq\common\Exception
     */
    public static final function loadValidationRules(string $key, string $file = null): array
    {
        $validations = self::loadValidations($file);

        empty($validations[$key]) && throw new Exception(
            'No rules found for key `%s`', $key
        );

        return $validations[$key];
    }

    /**
     * Run a validation for given data by rules & options, filtering/sanitizing `$data` argument
     * and filling `$errors` argument when validation fails.
     *
     * @param  ?array &$data
     * @param  ?array  $rules
     * @param  ?array  $options
     * @param  ?array &$errors
     * @return bool
     * @internal
     */
    protected final function runValidation(?array &$data, ?array $rules, ?array $options, ?array &$errors): bool
    {
        $errors = null;
        $result = (new Validation($rules, $options))->validate($data, $errors);

        $this->validated = $result;
        if ($errors !== null) {
            $this->validationErrors = $errors;
        }

        return $result;
    }
}
