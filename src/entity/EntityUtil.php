<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\entity;

/**
 * Internal entity utility class.
 *
 * @package froq\database\entity
 * @class   froq\database\entity\EntityUtil
 * @author  Kerem Güneş
 * @since   6.0
 * @internal
 */
class EntityUtil extends \StaticClass
{
    /**
     * Apply a deep array conversion.
     *
     * @param  array $data
     * @return array
     */
    public static function toArrayDeep(array $data): array
    {
        foreach ($data as &$item) {
            if ($item instanceof Entity || $item instanceof EntityList) {
                $item = $item->toArray(true);
            }
        }

        return $data;
    }

    /**
     * Apply a deep object conversion.
     *
     * @param  object $data
     * @return object
     */
    public static function toObjectDeep(object $data): object
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
                $item = $item->toArray();
            }
        }

        return $data;
    }

    /**
     * Convert given given object to JSON string applying filter/map params if given.
     *
     * Note: Map arguments (or `$data` only) must be passed byref for modifications or removals.
     *
     * @param  Entity|EntityList $object
     * @param  int               $flags
     * @param  callable|null     $filter
     * @param  callable|null     $map
     * @return string
     */
    public static function toJson(Entity|EntityList $object, int $flags = 0, callable $filter = null, callable $map = null): string
    {
        if ($object instanceof Entity) {
            $data = $object->toArray(true);

            if ($filter) {
                $temp = [];
                foreach ($data as $key => $value) {
                    $filter($value, $key) && $temp[$key] = $value;
                }

                [$data, $temp] = [$temp, null];
            }

            // Map arguments must be passed byref for modifications.
            if ($map) foreach ($data as $key => $value) {
                $map($value, $key, $data);
            }

            return (string) json_encode($data, $flags);
        } else {
            $json = [];

            foreach ($object->toArray() as $item) {
                if ($item instanceof Entity) {
                    $json[] = $item->toJson($flags, $filter, $map);
                } else {
                    // Fallback for non-entity items (in case).
                    $json[] = (string) json_encode($item, $flags);
                }
            }

            return '[' . join(',', $json) . ']';
        }
    }
}
