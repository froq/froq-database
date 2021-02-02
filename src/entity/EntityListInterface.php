<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\common\interface\Arrayable;
use Countable, JsonSerializable, ArrayAccess, IteratorAggregate;

/**
 * Entity List Interface.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\EntityListInterface
 * @author  Kerem Güneş
 * @since   4.8 Separated from EntityInterface.
 */
interface EntityListInterface extends Arrayable, Countable, JsonSerializable, ArrayAccess, IteratorAggregate
{}
