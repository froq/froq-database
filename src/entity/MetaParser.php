<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{MetaException, MetaFactory, Meta};
use froq\util\Objects;
use ReflectionClass, ReflectionProperty, ReflectionException;

final class MetaParser
{
    public static function parse(string $class, bool $withProperties = true): Meta
    {
        // Check MetaFactory cache for only "withProperties" parsing.
        if ($withProperties && MetaFactory::hasCacheItem($class)) {
            return MetaFactory::getCacheItem($class);
        }

        try {
            $classRef = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new MetaException($e);
        }

        $data = self::dataFromReflection($classRef);
        $data || throw new MetaException(
            'No meta attribute/annotation exists on class `%s`', $class
        );

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
            $types = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED;
            $props = [];

            foreach ($classRef->getProperties($types) as $propRef) {
                // We don't use static vars.
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
                     data: self::dataFromReflection($propRef),
                );
                $prop->setReflector($propRef);

                $props[$propName] = $prop;
            }

            $meta->setProperties($props);
        }

        return $meta;
    }

    public static function dataFromReflection(ReflectionClass|ReflectionProperty $ref): array
    {
        $data = [];

        if ($attributes = $ref->getAttributes()) {
            // Eg: #[meta(id:"id", table:"users", ..)]
            foreach ($attributes as $attribute) {
                $name = Objects::getShortName($attribute->getName());
                if (strtolower($name) == 'meta') {
                    $data = $attribute->getArguments();
                    break;
                }
            }
        } elseif ($annotations = $ref->getDocComment()) {
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
                    $refType = ($ref instanceof ReflectionClass) ? 'class' : 'property';

                    throw new MetaException(
                        'Failed to parse meta annotation of `%s` %s [error: %s]',
                        [$refName, $refType, strtolower($error)]
                    );
                }
            }
        }

        return $data;
    }
}
