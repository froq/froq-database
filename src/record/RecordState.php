<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

/**
 * @package froq\database\record
 * @object  froq\database\record\RecordState
 * @author  Kerem Güneş
 * @since   6.0
 */
class RecordState extends \State
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // With a fresh initial state.
        parent::__construct(saved: null, finded: null, removed: null);
    }
}
