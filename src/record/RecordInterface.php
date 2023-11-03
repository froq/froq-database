<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\record;

use froq\common\interface\{Arrayable, Objectable};

/**
 * @package froq\database\record
 * @class   froq\database\record\RecordInterface
 * @author  Kerem Güneş
 * @since   5.5
 */
interface RecordInterface extends Arrayable, Objectable, \ArrayAccess
{}
