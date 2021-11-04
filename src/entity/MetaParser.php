<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{MetaException, MetaFactory, Meta, EntityClassMeta, EntityPropertyMeta};
use froq\util\Objects;
use ReflectionClass, ReflectionProperty, ReflectionException;

final class MetaParser
{
    public static function parseClassMeta(string|object $class, bool $withProperties = true): EntityClassMeta|null
    {
        // When an object given.
        is_string($class) || $class = get_class($class);

        // Check MetaFactory cache for only "withProperties" parsing.
        if ($withProperties && MetaFactory::hasCacheItem($class)) {
            return MetaFactory::getCacheItem($class);
        }

        try {
            $classRef = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new MetaException($e);
        }

        $data = self::getData($classRef);
        if (!$data) {
            return null;
        }

        /** @var froq\database\entity\EntityClassMeta */
        $meta = MetaFactory::init(
            type: Meta::TYPE_CLASS,
           class: $class,
            name: $class,
            data: $data,
        );
        $meta->setReflector($classRef);

        // And add properties.
        if ($withProperties) {
            // We use only "public/protected" properties, not "static/private" ones.
            $types = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED;
            $props = [];

            foreach ($classRef->getProperties($types) as $propRef) {
                // We don't use static properties.
                if ($propRef->isStatic()) {
                    continue;
                }

                $propName  = $propRef->name;
                $propClass = $propRef->class;

                /** @var froq\database\entity\EntityPropertyMeta */
                $prop = MetaFactory::init(
                     type: Meta::TYPE_PROPERTY,
                    class: $propClass,
                     name: $propClass . '.' . $propName, // Fully-qualified property name.
                     data: self::getData($propRef),
                );
                $prop->setReflector($propRef);

                $props[$propName] = $prop;
            }

            $meta->setProperties($props);
        }

        return $meta;
    }

    public static function parsePropertyMeta(object|string $class, string $property): EntityPropertyMeta|null
    {
        // When an object given.
        is_string($class) || $class = get_class($class);

        // Check MetaFactory cache.
        if (MetaFactory::hasCacheItem($name = ($class .'.'. $property))) {
            return MetaFactory::getCacheItem($name);
        }

        try {
            $propRef = new ReflectionProperty($class, $property);
        } catch (ReflectionException $e) {
            throw new MetaException($e);
        }

        // We don't use static & private properties.
        if ($propRef->isStatic() || $propRef->isPrivate()) {
            return null;
        }

        $data = self::getData($classRef);
        if (!$data) {
            return null;
        }

        $propName  = $propRef->name;
        $propClass = $propRef->class;

        /** @var froq\database\entity\EntityPropertyMeta */
        $meta = MetaFactory::init(
             type: Meta::TYPE_PROPERTY,
            class: $propClass,
             name: $propClass . '.' . $propName, // Fully-qualified property name.
             data: $data,
        );
        $meta->setReflector($propRef);

        return $meta;
    }

    public static function getData(ReflectionClass|ReflectionProperty $ref): array|null
    {
        // Eg: #[meta(id:"id", table:"users", ..)]
        if ($attributes = $ref->getAttributes()) {
            return self::getDataFromAttributes($attributes);
        }

        // Eg: @meta(id:"id", table:"users", ..)
        // Eg: @meta(id="id", table="users", ..)
        if ($annotations = $ref->getDocComment()) {
            return self::getDataFromAnnotations($annotations, $ref);
        }

        return null;
    }

    private static function getDataFromAttributes(array $attributes): array|null
    {
        foreach ($attributes as $attribute) {
            $name = Objects::getShortName($attribute->getName());
            if (strtolower($name) == 'meta') {
                return $attribute->getArguments();
            }
        }

        return null;
    }

    private static function getDataFromAnnotations(string $annotations, ReflectionClass|ReflectionProperty $ref): array|null
    {
        // Eg: @meta(id:"id", table:"users", ..)
        // Eg: @meta(id="id", table="users", ..)
        if (preg_match('~@meta\s*\((.+)\)~si', $annotations, $match)) {
            $lines = preg_split('~\n~', $match[1], -1, 1);

            // Converting to JSON.
            foreach ($lines as &$line) {
                $line = preg_replace('~^[*\s]+|[\s]+$~', '', $line);

                // Comment-outs.
                if (str_starts_with($line, '//')) {
                    $line = '';
                    continue;
                }

                // Prepare entity class & JSON field names.
                $line = preg_replace('~\\\\(?!["])~', '\\\\\\\\\1', $line);
                $line = preg_replace('~(\w{2,})\s*[:=](?![=])~', '"\1":', $line);
            }

            $json = '{'. trim(join(' ', array_filter($lines)), ',') .'}';
            $data = json_decode($json, true);

            if ($error = json_error_message()) {
                // Prepare a fully-qualified name & reflection type.
                $refName = isset($ref->class) ? $ref->class . '.' . $ref->name : $ref->name;
                $refType = isset($ref->class) ? 'property' : 'class';

                throw new MetaException(
                    'Failed to parse meta annotation of `%s` %s [error: %s]',
                    [$refName, $refType, strtolower($error)]
                );
            }

            return $data;
        }

        return null;
    }
}
