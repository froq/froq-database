<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\util\misc\{Storage, Package};

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

    /**
     * Store given object for serialize operations keeping private stuff original.
     *
     * @param  Entity|EntityList $object
     * @return array
     */
    public static function store(Entity|EntityList $object): array
    {
        $pack = new Package(data: $object->toArray());
        $pack->manager = $object->getManager();

        if ($object instanceof Entity) {
            $pack->states = $object->getStates();
        } else {
            $pack->pager = $object->getPager();
        }

        // Keep UUID public to take back from unserialize call.
        $pack->data['@uuid'] = $uuid = uuid();

        Storage::store($uuid, $pack);

        return $pack->data;
    }

    /**
     * Unstore given object for unserialize operations keeping private stuff original.
     *
     * @param  Entity|EntityList $object
     * @return array
     */
    public static function unstore(Entity|EntityList $object, array $data): array
    {
        $pack = Storage::unstore($data['@uuid']);
        $pack->manager && $object->setManager($pack->manager);

        if ($object instanceof Entity) {
            $object->initState(...$pack->states);
        } else {
            $pack->pager && $object->setPager($pack->pager);
        }

        // Drop unowned UUID field.
        unset($pack->data['@uuid']);

        return $pack->data;
    }

    /**
     * Convert given given object to JSON string applying filter/map params if given.
     *
     * Note: Map arguments (or $data only) must be passed byref for modifications or removals.
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
            $json = '';

            foreach ($object->toArray() as $item) {
                if ($item instanceof Entity) {
                    $json .= $item->toJson($flags, $filter, $map);
                } else {
                    $json .= (string) json_encode($object, $flags);
                }
            }

            return $json;
        }
    }
}