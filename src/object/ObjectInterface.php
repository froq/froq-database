<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\object;

use froq\common\interface\Arrayable;
use Countable, JsonSerializable, ArrayAccess, IteratorAggregate;

/**
 * Object Interface.
 *
 * @package froq\database\object
 * @object  froq\database\object\ObjectInterface
 * @author  Kerem Güneş
 * @since   4.6, 4.8, 5.0
 */
interface ObjectInterface extends Arrayable, Countable, JsonSerializable, ArrayAccess, IteratorAggregate
{}
