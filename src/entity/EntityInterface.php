<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\common\interface\{Arrayable, Objectable, Jsonable};

/**
 * @package froq\database\entity
 * @object  froq\database\entity\EntityInterface
 * @author  Kerem Güneş
 * @since   5.0
 */
interface EntityInterface extends Arrayable, Objectable, Jsonable, \ArrayAccess
{}
