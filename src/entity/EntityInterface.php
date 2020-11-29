<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\common\interfaces\Arrayable;
use Countable, JsonSerializable, ArrayAccess, IteratorAggregate;

/**
 * Entity Interface.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\EntityInterface
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.6, 4.8 Separated from EntityInterface.
 */
interface EntityInterface extends Arrayable, Countable, JsonSerializable, ArrayAccess, IteratorAggregate
{}
