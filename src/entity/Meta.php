<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\database\entity\MetaException;
use froq\util\Objects;
use Reflector;

class Meta
{
    public const TYPE_CLASS    = 1,
                 TYPE_METHOD   = 2, // Not implemented (reserved).
                 TYPE_PROPERTY = 3;

    protected int $type;
    protected array $data;
    protected string $name; // @todo class name, var name
    protected string $class;

    private Reflector $reflector;

    public function __construct(int $type, array $data, string $name, string $class)
    {
        foreach ($data as $key => &$value) {
            switch ($key) {
                case 'entity':
                case 'entityList':
                case 'repository':
                    $value = str_replace('.', '\\', $value);
                    if (!str_contains($value, '\\')) {
                        $value = Objects::getNamespace($class) . '\\' . $value;
                    }
                    break;
            }
        }

        $this->type  = $type;
        $this->data  = $data;
        $this->name  = $name;
        $this->class = $class;
    }

    public final function getType(): int
    {
        return $this->type;
    }
    public final function getData(): array
    {
        return $this->data;
    }
    public final function getClass(): string
    {
        return $this->class;
    }
    public final function getName(): string
    {
        return $this->name;
    }

    public final function getOption(string $name, $default = null): bool
    {
        return (bool) $this->getDataField($name, $default);
    }

    public final function hasDataField(string $key): bool
    {
        return array_isset($this->data, $key);
    }
    public final function getDataField(string $key, $default = null)
    {
        return array_fetch($this->data, $key, $default);
    }

    public final function isTypeClass(): bool
    {
        return $this->type == self::TYPE_CLASS;
    }
    public final function isTypeProperty(): bool
    {
        return $this->type == self::TYPE_PROPERTY;
    }

    public final function setReflector(Reflector $reflector): void
    {
        $this->reflector = $reflector;
    }
    public final function getReflector(): Reflector
    {
        return $this->reflector;
    }
}
