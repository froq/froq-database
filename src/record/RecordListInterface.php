<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\record;

use froq\common\interface\Arrayable;

/**
 * @package froq\database\record
 * @object  froq\database\record\RecordListInterface
 * @author  Kerem Güneş
 * @since   5.5
 */
interface RecordListInterface extends Arrayable, \ArrayAccess
{}
