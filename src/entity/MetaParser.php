<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\util\Objects;
use ReflectionClass, ReflectionProperty, ReflectionException;

/**
 * A parser class, used for parsing entity classes's metadata info into `ClassMeta`
 * & `PropertyMeta` classes.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\MetaParser
 * @author  Kerem Güneş
 * @since   5.0
 */
final class MetaParser
{
    /**
     * Parse a class metadata.
     *
     * @param  object $class
     * @param  bool   $withProperties
     * @return froq\database\entity\ClassMeta|null
     * @throws froq\database\entity\MetaException
     */
    public static function parseClassMeta(string|object $class, bool $withProperties = true): ClassMeta|null
    {
        is_object($class) && $class = $class::class;

        // Check MetaFactory cache for only "withProperties" parsing.
        if ($withProperties && MetaFactory::hasCacheItem($class)) {
            return MetaFactory::getCacheItem($class);
        }

        try {
            $classRef = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new MetaException($e);
        }

        $data = self::getDataFrom($classRef);
        if (!$data) {
            return null;
        }

        /** @var froq\database\entity\ClassMeta */
        $meta = MetaFactory::initClassMeta($class, $data);
        $meta->setReflection($classRef);

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

                // Skip non @meta stuff.
                $data = self::getDataFrom($propRef);
                if ($data === null) {
                    continue;
                }

                /** @var froq\database\entity\PropertyMeta */
                $prop = MetaFactory::initPropertyMeta($propName, $propClass, $data);
                $prop->setReflection($propRef);

                $props[$propName] = $prop;
            }

            $meta->setProperties($props);
        }

        return $meta;
    }

    /**
     * Parse a property metadata.
     *
     * @param  object $class
     * @param  string $property
     * @return froq\database\entity\PropertyMeta|null
     * @throws froq\database\entity\MetaException
     */
    public static function parsePropertyMeta(object|string $class, string $property): PropertyMeta|null
    {
        is_object($class) && $class = $class::class;

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

        // Skip non @meta stuff.
        $data = self::getDataFrom($propRef->getDeclaringClass());
        if ($data === null) {
            return null;
        }

        $propName  = $propRef->name;
        $propClass = $propRef->class;

        /** @var froq\database\entity\PropertyMeta */
        $meta = MetaFactory::initPropertyMeta($propName, $propClass, $data);
        $meta->setReflection($propRef);

        return $meta;
    }

    /**
     * Get data from a reflection class/property.
     *
     * @param  ReflectionClass|ReflectionProperty $ref
     * @return array|null
     */
    private static function getDataFrom(ReflectionClass|ReflectionProperty $ref): array|null
    {
        // Eg: #[meta(table:"users", ..)]
        if ($attributes = $ref->getAttributes()) {
            return self::getDataFromAttributes($attributes);
        }

        // Eg: @meta(table:"users", ..)
        // Eg: @meta(table="users", ..)
        if ($annotations = $ref->getDocComment()) {
            return self::getDataFromAnnotations($annotations, $ref);
        }

        return null;
    }

    /**
     * Get data from given attributes.
     *
     * @param  array $attributes
     * @return array|null
     */
    private static function getDataFromAttributes(array $attributes): array|null
    {
        // Eg: #[meta(table:"users", ..)]
        foreach ($attributes as $attribute) {
            $name = Objects::getShortName($attribute->getName());
            if (strtolower($name) == 'meta') {
                return $attribute->getArguments();
            }
        }

        return null;
    }

    /**
     * Get data from given annotations.
     *
     * @param  string                             $annotations
     * @param  ReflectionClass|ReflectionProperty $ref
     * @return array|null
     */
    private static function getDataFromAnnotations(string $annotations, ReflectionClass|ReflectionProperty $ref): array|null
    {
        // Eg: @meta, for only select fields usage.
        if (preg_match('~@meta[^(]~si', $annotations)) {
            return [];
        }

        // Eg: @meta(table:"users", ..)
        // Eg: @meta(table="users", ..)
        if (preg_match('~@meta\s*\((.+)\)~si', $annotations, $match)) {
            $lines = preg_split('~\n~', $match[1], -1, 1);

            // Converting to JSON.
            foreach ($lines as &$line) {
                $line = preg_replace('~^[\*\s]+|[\s]+$~', '', $line);

                // Comment-outs.
                if (str_starts_with($line, '//')) {
                    $line = '';
                    continue;
                }

                // Prepare entity class & JSON fields.
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
