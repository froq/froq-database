<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

/**
 * @package froq\database\entity
 * @object  froq\database\entity\EntityUtil
 * @author  Kerem Güneş
 * @since   6.0
 * @static
 */
class EntityUtil extends \StaticClass
{
    /**
     * Make a deep array, used by `toArray()` methods of `Entity` and `EntityList` classes.
     *
     * @param  array $data
     * @return array
     */
    public static function toDeepArray(array $data): array
    {
        foreach ($data as &$item) {
            if ($item instanceof Entity || $item instanceof EntityList) {
                $item = $item->toArray(true);
            }
        }

        return $data;
    }

    /**
     * Make a deep object, used by `toObject()` method of `Entity` class.
     *
     * @param  object $data
     * @return object
     */
    public static function toDeepObject(object $data): object
    {
        foreach ($data as &$item) {
            if ($item instanceof Entity) {
                $item = $item->toObject(true);
            } elseif ($item instanceof EntityList) {
                foreach ($item as $i => $entity) {
                    if ($entity instanceof Entity) {
                        $item[$i] = $entity->toObject(true);
                    }
                }
            }
        }

        return $data;
    }
}
