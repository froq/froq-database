<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\entity;

use froq\common\interface\Arrayable;

/**
 * @package froq\database\entity
 * @class   froq\database\entity\EntityListInterface
 * @author  Kerem Güneş
 * @since   5.0
 */
interface EntityListInterface extends Arrayable, \ArrayAccess
{}
