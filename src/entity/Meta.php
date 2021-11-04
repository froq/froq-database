<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\MetaException;
use froq\util\Objects;
use ReflectionClass, ReflectionProperty;

class Meta
{
    public const TYPE_CLASS    = 1,
                 TYPE_PROPERTY = 2,
                 TYPE_METHOD   = 3; // Not implemented (reserved).

    protected int $type;
    protected string $name;
    protected string $class;
    protected array $data = [];

    private ReflectionClass|ReflectionProperty $reflector;

    public function __construct(int $type, string $name, string $class, array $data = null)
    {
        $this->type  = $type;
        $this->name  = $name;
        $this->class = $class;

        // Data may be delayed.
        $data && $this->setData($data);
    }

    public final function getType(): int
    {
        return $this->type;
    }
    public final function getName(): string
    {
        return $this->name;
    }
    public final function getShortName(): string
    {
        return explode('.', $this->name)[1];
    }
    public final function getClass(): string
    {
        return $this->class;
    }

    public final function isTypeClass(): bool
    {
        return ($this->type == self::TYPE_CLASS);
    }
    public final function isTypeProperty(): bool
    {
        return ($this->type == self::TYPE_PROPERTY);
    }

    public final function setData(array $data): void
    {
        // Annotation "list" is for only classes.
        if (isset($data['list']) && $this->isTypeProperty()) {
            throw new MetaException(
                'Invalid annotation directive `list` for property `%s`',
                $this->name
            );
        }

        foreach ($data as $key => &$value) {
            switch ($key) {
                case 'entity':
                case 'entityList':
                case 'repository':
                case 'list':
                    // Dots mean namespace separator ("\").
                    $value = str_replace('.', '\\', $value);

                    // When a non-fully-qualified class name given.
                    if (!str_contains($value, '\\')) {
                        $value = Objects::getNamespace($this->class) . '\\' . $value;
                    }
                    break;
            }
        }

        $this->data = $data;
    }
    public final function getData(): array|null
    {
        return $this->data ?: null;
    }

    public final function hasDataField(string $key): bool
    {
        return array_isset($this->data, $key);
    }
    public final function getDataField(string $key, $default = null)
    {
        return array_fetch($this->data, $key, $default);
    }

    public final function getOption(string $name, $default = null): bool
    {
        return (bool) $this->getDataField($name, $default);
    }

    public final function setReflector(ReflectionClass|ReflectionProperty $reflector): void
    {
        $this->reflector = $reflector;
    }
    public final function getReflector(): ReflectionClass|ReflectionProperty|null
    {
        return $this->reflector ?? null;
    }
}
