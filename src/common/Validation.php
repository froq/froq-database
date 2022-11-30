<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\common;

use froq\validation\{Validation as BaseValidation};

/**
 * Validation wrapper, holds validation related stuff.
 *
 * @package froq\database\common
 * @object  froq\database\common\Validation
 * @author  Kerem Güneş
 * @since   6.0
 */
class Validation
{
    /** Rules & options */
    protected array $rules, $options;

    /** Validation errors. */
    protected ?array $errors = null;

    /** Validation result. */
    protected ?bool $result = null;

    /**
     * Constructor.
     *
     * @param array|null $rules
     * @param array|null $options
     */
    public function __construct(array $rules = null, array $options = null)
    {
        $rules   && $this->rules   = $rules;
        $options && $this->options = $options;
    }

    /**
     * Get errors.
     *
     * @return array|null
     */
    public function errors(): array|null
    {
        return $this->errors;
    }

    /**
     * Get result.
     *
     * @return bool|null
     */
    public function result(): bool|null
    {
        return $this->result;
    }

    /**
     * Reset errors & result.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->errors = null;
        $this->result = null;
    }

    /**
     * Set rules.
     *
     * @param  array $rules
     * @return self
     */
    public function setRules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Get rules.
     *
     * @return ?array
     */
    public function getRules(): ?array
    {
        return $this->rules ?? null;
    }

    /**
     * Set options.
     *
     * @param  array $options
     * @return self
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get options.
     *
     * @return ?array
     */
    public function getOptions(): ?array
    {
        return $this->options ?? null;
    }

    /**
     * Run validation for given data by rules & options, filtering/sanitizing `$data` argument
     * and filling `$errors` argument when validation fails.
     *
     * @param  ?array &$data
     * @param  ?array &$errors
     * @param  ?array  $rules
     * @param  ?array  $options
     * @return bool
     */
    public function run(?array &$data, ?array &$errors, ?array $rules, ?array $options): bool
    {
        $this->result = (new BaseValidation($rules, $options))->validate($data, $errors);

        if ($errors !== null) {
            $this->errors = $errors;
        }

        return $this->result;
    }
}
