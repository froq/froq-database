<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\common;

use froq\validation\{Validation as BaseValidation};

/**
 * @package froq\database\common
 * @object  froq\database\common\Validation
 * @author  Kerem Güneş
 * @since   6.0
 */
class Validation
{
    /** @var array, array */
    protected array $rules, $options;

    /** @var ?array */
    protected ?array $errors = null;

    /** @var ?bool */
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
     * @return ?array
     */
    public function errors(): ?array
    {
        return $this->errors;
    }

    /**
     * Get result.
     *
     * @return ?bool
     */
    public function result(): ?bool
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
     * @param  ?array  $rules
     * @param  ?array  $options
     * @param  ?array &$errors
     * @return bool
     */
    public function run(?array &$data, ?array $rules, ?array $options, ?array &$errors): bool
    {
        $errors = null;
        $result = (new BaseValidation($rules, $options))->validate($data, $errors);

        $this->result = $result;

        if ($errors !== null) {
            $this->errors = $errors;
        }

        return $result;
    }
}
