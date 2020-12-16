<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database\trait;

use froq\common\Exception;
use froq\validation\Validation;

/**
 * Validation Trait.
 *
 * Represents a trait entity that holds validation properties and methods with its state and errors.
 *
 * @package froq\database\trait
 * @object  froq\database\trait\ValidationTrait
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   5.0
 * @internal
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
        return $this->getValidationErrors();
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
        defined('APP_DIR') || throw new Exception('No APP_DIR defined');

        // Try to load default file from config directory (or directory, eg: config/validations/user).
        $file = APP_DIR . '/app/config/' . ($file ?: 'validations') . '.php';
        if (!is_file($file)) {
            throw new Exception('No validations file exists such `%s`', $file);
        }

        $validations = include $file;
        if (!is_array($validations)) {
            throw new Exception('Validation files must return an array, %s returned',
                get_type($validations));
        }

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

        if (empty($validations[$key])) {
            throw new Exception('No rules found for key `%s`', $key);
        }

        return $validations[$key];
    }

    /**
     * Run a validation for given data by rules & options, filtering/sanitizing `$data` argument and filling
     * `$errors` argument when validation fails.
     *
     * @param  array &$data
     * @param  array  $rules
     * @param  array  $options
     * @param  array &$errors
     * @return bool
     * @throws froq\common\Exception
     * @internal
     */
    protected final function runValidation(?array &$data, ?array $rules, ?array $options, ?array &$errors): bool
    {
        if (empty($rules)) {
            throw new Exception('No validation rules set yet, call setValidationRules() or define'
                . ' $validationRules property on %s class', static::class);
        }

        $validation = new Validation($rules, $options);

        $this->validated = $validation->validate($data, $errors);

        if ($errors !== null) {
            $this->validationErrors = $errors;
        }

        return $this->validated;
    }
}
