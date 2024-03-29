<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\trait;

use froq\database\common\Validation;

/**
 * A trait, provides validation related stuff.
 *
 * @package froq\database\trait
 * @class   froq\database\trait\ValidationTrait
 * @author  Kerem Güneş
 * @since   5.0
 */
trait ValidationTrait
{
    /** Validation instance. */
    protected Validation $validation;

    /**
     * Set validation.
     *
     * @param  froq\database\common\Validation
     * @return self
     */
    public function setValidation(Validation $validation): self
    {
        $this->validation = $validation;

        return $this;
    }

    /**
     * Get validation.
     *
     * @return froq\database\common\Validation|null
     */
    public function getValidation(): Validation|null
    {
        return $this->validation ?? null;
    }

    /**
     * Set validations.
     *
     * @param  array|null $validations
     * @return self
     */
    public function setValidations(array|null $validations): self
    {
        $rules = $options = null;

        // Validations can be combined or simple array'ed.
        if ($validations) {
            if (isset($validations['@rules'])) {
                $rules = array_pull($validations, '@rules');
            }
            if (isset($validations['@options'])) {
                $options = array_pull($validations, '@options');
            }

            // Simple array'ed if no "@rules" field given.
            $rules ??= $validations;
        }

        return $this->setValidation(new Validation($rules, $options));
    }

    /**
     * Get validations.
     *
     * @return array|null
     */
    public function getValidations(): array|null
    {
        if ($validation = $this->getValidation()) {
            return [
                '@rules'   => $validation->getRules(),
                '@options' => $validation->getOptions()
            ];
        }

        return null;
    }
}
