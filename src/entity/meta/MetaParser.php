<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity\meta;

use froq\util\Objects;
use ReflectionClass, ReflectionProperty, ReflectionException;

/**
 * A parser class, used for parsing entity classes's metadata info as
 * `ClassMeta` & `PropertyMeta` classes.
 *
 * @package froq\database\entity\meta
 * @object  froq\database\entity\meta\MetaParser
 * @author  Kerem Güneş
 * @since   5.0
 */
final class MetaParser
{
    /**
     * Parse class meta.
     *
     * @param  string|object $class
     * @param  bool          $withProperties
     * @return froq\database\entity\meta\ClassMeta|null
     * @throws froq\database\entity\meta\MetaException
     */
    public static function parseClassMeta(string|object $class, bool $withProperties = true): ClassMeta|null
    {
        is_object($class) && $class = $class::class;

        // Check cache & a missing stuff (cos' of APCu cache).
        if ($withProperties && ($classMeta = MetaCache::getItem($class))) {
            if (!$classMeta->getReflection()) {
                $classMeta->setReflection(
                    $classRef = new ReflectionClass($class)
                );
            }
            if (!$classMeta->getProperties()) {
                $classRef ??= new ReflectionClass($class);
                foreach ($classRef->getProperties() as $propRef) {
                    $propertyMeta = self::parsePropertyMeta($classRef->name, $propRef->name);
                    $propertyMeta && $classMeta->addProperty($propRef->name, $propertyMeta);
                }
            }

            return $classMeta;
        }

        try {
            $classRef ??= new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new MetaException($e);
        }

        $data = self::getDataFrom($classRef);
        if (!$data) {
            return null;
        }

        /** @var froq\database\entity\meta\ClassMeta */
        $classMeta = MetaFactory::initClassMeta($class, $data);
        $classMeta->setReflection($classRef);

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

                $data = self::getDataFrom($propRef);

                // Skip non @meta stuff.
                if ($data === null) {
                    continue;
                }

                /** @var froq\database\entity\meta\PropertyMeta */
                $propertyMeta = MetaFactory::initPropertyMeta($propRef->name, $propRef->class, $data);
                $propertyMeta->setReflection($propRef);

                $props[$propRef->name] = $propertyMeta;
            }

            $classMeta->setProperties($props);
        }

        return $classMeta;
    }

    /**
     * Parse property meta.
     *
     * @param  string|object $class
     * @param  string        $property
     * @return froq\database\entity\PropertyMeta|null
     * @throws froq\database\entity\MetaException
     */
    public static function parsePropertyMeta(string|object $class, string $property): PropertyMeta|null
    {
        is_object($class) && $class = $class::class;

        // Check cache & add missing stuff (cos' of APCu cache).
        if ($propertyMeta = MetaCache::getItem($class .'.'. $property)) {
            if (!$propertyMeta->getReflection()) {
                $propertyMeta->setReflection(
                    $propRef = new ReflectionProperty($class, $property)
                );
            }

            return $propertyMeta;
        }

        try {
            $propRef ??= new ReflectionProperty($class, $property);
        } catch (ReflectionException $e) {
            throw new MetaException($e);
        }

        // We don't use static & private properties.
        if ($propRef->isStatic() || $propRef->isPrivate()) {
            return null;
        }

        $data = self::getDataFrom($propRef);

        // Skip non @meta stuff.
        if ($data === null) {
            return null;
        }

        /** @var froq\database\entity\meta\PropertyMeta */
        $propertyMeta = MetaFactory::initPropertyMeta($propRef->name, $propRef->class, $data);
        $propertyMeta->setReflection($propRef);

        return $propertyMeta;
    }

    /**
     * Get data from a reflection class/property annotations or attributes.
     */
    private static function getDataFrom(ReflectionClass|ReflectionProperty $ref): array|null
    {
        if ($attributes = $ref->getAttributes()) {
            return self::getDataFromAttributes($attributes);
        }

        if ($annotations = $ref->getDocComment()) {
            return self::getDataFromAnnotations($annotations, $ref);
        }

        return null;
    }

    /**
     * Get data from attributes.
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
     * Get data from annotations.
     *
     * @throws froq\database\entity\meta\MetaException
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
