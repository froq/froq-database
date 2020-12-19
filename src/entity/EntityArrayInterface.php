<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\common\interface\Arrayable;
use Countable, JsonSerializable, ArrayAccess, IteratorAggregate;

/**
 * Entity Array Interface.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\EntityArrayInterface
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.8 Separated from EntityInterface.
 */
interface EntityArrayInterface extends Arrayable, Countable, JsonSerializable, ArrayAccess, IteratorAggregate
{}
