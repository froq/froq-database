<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\{MetaException, MetaFactory, Meta};
use froq\util\Objects;
use Reflector, ReflectionClass, ReflectionProperty, ReflectionException;

final class MetaParser
{
    public static function parse(string $class, bool $withProperties = true): Meta
    {
        try {
            $ref = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new MetaException($e);
        }

        $data = self::dataFromReflection($ref);
        $data || throw new MetaException(
            'No meta attribute/annotation exists on class `%s`', $class
        );

        /** @var froq\database\entity\EntityClassMeta */
        $meta = MetaFactory::init(Meta::TYPE_CLASS, $data, $class, $class);
        $meta->setReflector($ref);

        // And add properties.
        if ($withProperties) {
            $types = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED;
            $props = [];

            foreach ($ref->getProperties($types) as $pref) {
                // We don't use static vars.
                if ($pref->isStatic()) {
                    continue;
                }

                $class = $pref->getDeclaringClass();
                $pname = $pref->getName();
                $cname = $class->getName();

                /** @var froq\database\entity\EntityPropertyMeta */
                $prop = MetaFactory::init(
                    Meta::TYPE_PROPERTY,
                    data: self::dataFromReflection($pref),
                    name: $cname . '.' . $pname, // Fully qualified property name.
                    class: $cname,
                );

                // // Add setter/getter methods (if defined & public).
                // $setter = 'set' . ucfirst($pref->name);
                // $getter = 'get' . ucfirst($pref->name);

                // if ($class->hasMethod($setter) && $class->getMethod($setter)->isPublic()) {
                //     $prop->setSetterMethod($setter);
                // }
                // if ($class->hasMethod($getter) && $class->getMethod($getter)->isPublic()) {
                //     $prop->setGetterMethod($getter);
                // }

                // // Add prop to ref as link-back.
                // $pref->prop = $prop;

                // Add reflector.
                $prop->setReflector($pref);

                $props[$pname] = $prop;
            }

            $meta->setProperties($props);
        }

        return $meta;
    }

    public static function dataFromReflection(Reflector $ref): array
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
                    $class = strtolower(str_replace('Reflection', '', $ref::class));
                    throw new MetaException(
                        'Failed to parse meta annotation of `%s` %s [error: %s]',
                        [$ref->name, $class, strtolower($error)]
                    );
                }
            }
        }

        // JSON format used for validations.
        if (isset($data['validation']) && is_string($data['validation'])) {
            $data['validation'] = json_decode($data['validation'], true);

            if ($error = json_error_message()) {
                $class = strtolower(str_replace('Reflection', '', $ref::class));
                throw new MetaException(
                    'Failed to parse meta validation of `%s` %s [error: %s]',
                    [$ref->name, $class, strtolower($error)]
                );
            }
        }

        // Dots may be used for entity (class) names.
        if (isset($data['entity']) && str_contains($data['entity'], '.')) {
            $data['entity'] = str_replace('.', '\\', $data['entity']);
        }

        return $data;
    }
}
