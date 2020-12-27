<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\trait;

use froq\database\{trait\DbTrait, trait\TableTrait, trait\ValidationTrait};
use froq\common\trait\{DataTrait, DataLoadTrait, DataMagicTrait, OptionTrait};

/**
 * Record Trait.
 *
 * Represents a trait entity that used in `froq\database\record` and holds record related stuff.
 *
 * @package froq\database\trait
 * @object  froq\database\trait\RecordTrait
 * @author  Kerem Güneş
 * @since   5.0
 * @internal
 */
trait RecordTrait
{
    /**
     * @see froq\database\trait\DbTrait
     * @see froq\database\trait\TableTrait
     * @see froq\database\trait\ValidationTrait
     */
    use DbTrait, TableTrait, ValidationTrait;

    /** @see froq\common\trait\OptionTrait */
    use OptionTrait;

    /** @var array */
    protected static array $optionsDefault = [
        'transaction' => true, // Whether save actions will be done in a transaction wrap.
        'sequence'    => true, // Whether saved record has a ID sequence.
        'return'      => null, // Whether returning any field(s) or current data (for "returning" clause).
        'fetch'       => null, // Fetch type.
    ];
}
