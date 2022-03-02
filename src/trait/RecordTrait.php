<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\trait;

use froq\common\trait\{DataTrait, DataLoadTrait, DataAccessTrait, DataAccessMagicTrait,
    DataCountTrait, DataEmptyTrait, DataToArrayTrait, DataToObjectTrait, OptionTrait};
use froq\validation\ValidationError;

/**
 * Record Trait.
 *
 * A trait, used in `froq\database\record` only and holds record related stuff.
 *
 * @package froq\database\trait
 * @object  froq\database\trait\RecordTrait
 * @author  Kerem Güneş
 * @since   5.0
 * @internal
 */
trait RecordTrait
{
    /** @see froq\common\trait\OptionTrait */
    use OptionTrait;

    /**
     * @see froq\database\trait\DbTrait
     * @see froq\database\trait\TableTrait
     * @see froq\database\trait\ValidationTrait
     */
    use DbTrait, TableTrait, ValidationTrait;

    /**
     * @see froq\common\trait\DataTrait
     * @see froq\common\trait\DataLoadTrait
     * @see froq\common\trait\DataAccessTrait
     * @see froq\common\trait\DataAccessMagicTrait
     * @see froq\common\trait\DataCountTrait
     * @see froq\common\trait\DataEmptyTrait
     * @see froq\common\trait\DataToArrayTrait
     * @see froq\common\trait\DataToObjectTrait
     */
    use DataTrait, DataLoadTrait, DataAccessTrait, DataAccessMagicTrait, DataCountTrait, DataEmptyTrait,
        DataToArrayTrait, DataToObjectTrait;

    /** @var array */
    protected array $data = [];

    /** @var array */
    protected static array $optionsDefault = [
        'transaction' => true, // Whether save actions will be done in a transaction wrap.
        'sequence'    => true, // Whether saved record has a ID sequence.
        'validate'    => true,
        'return'      => null, // Whether returning any field(s) or current data (for "returning" clause).
        'fetch'       => null, // Fetch type.
    ];

    /**
     * Prepare given or own data running validation, throw if validation fails and silent option is not true.
     *
     * @param  array|null &$data
     * @param  bool        $silent
     * @return self|null
     * @throws froq\validation\ValidationError
     */
    public final function prepare(array &$data = null, bool $silent = false): self|null
    {
        $this->isValid($data, $errors);

        if (!$errors) {
            return $this;
        }
        if ($silent) {
            return null;
        }

        throw new ValidationError('Cannot prepare record (%s), validation failed [tip: run prepare()'
            . ' in a try/catch block and use errors() to see error details]', $this::class, errors: $errors);
    }
}
