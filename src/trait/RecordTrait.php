<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\trait;

use froq\common\trait\{DataTrait, DataAccessTrait, DataAccessMagicTrait};
use froq\collection\trait\{EmptyTrait, ToArrayTrait, ToObjectTrait};
use froq\validation\ValidationError;

/**
 * A trait, used in `froq\database\record` only and provides record related stuff.
 *
 * @package froq\database\trait
 * @object  froq\database\trait\RecordTrait
 * @author  Kerem Güneş
 * @since   5.0
 * @internal
 */
trait RecordTrait
{
    use DbTrait, TableTrait, ValidationTrait;
    use EmptyTrait, ToArrayTrait, ToObjectTrait;
    use DataTrait, DataAccessTrait, DataAccessMagicTrait;

    /** Data as map. */
    protected array $data = [];

    /** Options for queries. */
    protected array $options = [];

    /** Default options. */
    protected static array $optionsDefault = [
        'transaction' => true, // Whether save actions will be done in a transaction wrap.
        'sequence'    => true, // Whether saved record has a ID sequence.
        'validate'    => true,
        'return'      => null, // Whether returning any field(s) or current data (for "returning" clause).
        'fetch'       => null, // Fetch type.
    ];

    /**
     * Set options.
     *
     * @param  array|null $options
     * @return self
     * @since  6.0
     */
    public final function setOptions(array|null $options): self
    {
        $this->options = array_options($options, static::$optionsDefault);

        return $this;
    }

    /**
     * Get options.
     *
     * @return array
     */
    public final function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Prepare given or own data running validation, throw if validation fails and silent option is not true.
     *
     * @param  array|null &$data
     * @param  array|null &$errors
     * @param  bool        $silent
     * @return self|null
     * @throws froq\validation\ValidationError
     */
    public final function prepare(array &$data = null, array &$errors = null, bool $silent = false): self|null
    {
        $this->isValid($data, $errors);

        if (!$errors) {
            return $this;
        }
        if ($silent) {
            return null;
        }

        throw new ValidationError(
            'Cannot prepare %s, validation failed and $silent argument is false [tip: %s]',
            [static::class, ValidationError::tip()],
            errors: $errors
        );
    }
}
