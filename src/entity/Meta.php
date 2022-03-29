<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity;

use froq\util\Objects;
use ReflectionClass, ReflectionProperty;

/**
 * Meta.
 *
 * Base metadata class, used for `ClassMeta` & `PropertyMeta` classes internally.
 *
 * @package froq\database\entity
 * @object  froq\database\entity\Meta
 * @author  Kerem Güneş
 * @since   5.0
 * @internal
 */
class Meta
{
    /** @const int */
    public const TYPE_CLASS    = 1,
                 TYPE_PROPERTY = 2,
                 TYPE_METHOD   = 3; // Not implemented (reserved).

    /** @var ReflectionClass|ReflectionProperty */
    private ReflectionClass|ReflectionProperty $reflection;

    /** @var int */
    private int $type;

    /** @var string */
    private string $name;

    /** @var string */
    private string $class;

    /** @var array */
    protected array $data = [];

    /**
     * Constructor.
     *
     * @param int        $type
     * @param string     $name
     * @param string     $class
     * @param array|null $data
     */
    public function __construct(int $type, string $name, string $class, array $data = null)
    {
        $name = self::prepareName($type, $name, $class);

        $this->type  = $type;
        $this->name  = $name;
        $this->class = $class;

        // Data may be delayed.
        $data && $this->setData($data);
    }

    /**
     * Get type.
     *
     * @return int
     */
    public final function getType(): int
    {
        return $this->type;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public final function getName(): string
    {
        return $this->name;
    }

    /**
     * Get short name.
     *
     * @return string
     */
    public final function getShortName(): string
    {
        return match ($this->type) {
            self::TYPE_CLASS    => last(explode('\\', $this->name)),
            self::TYPE_PROPERTY => last(explode('.', $this->name)),
        };
    }

    /**
     * Get class.
     *
     * @return string
     */
    public final function getClass(): string
    {
        return $this->class;
    }

    /**
     * Set data.
     *
     * @param  array $data
     * @return void
     */
    public final function setData(array $data): void
    {
        foreach ($data as $key => &$value) {
            switch ($key) {
                case 'entity':
                case 'entityList':
                case 'repository':
                case 'list':
                    $value = trim($value);
                    if ($value === '') {
                        continue 2;
                    }

                    // Dots mean namespace separator ("\").
                    $value = str_replace('.', '\\', $value);

                    // When a non-fully-qualified class name given.
                    if (!str_contains($value, '\\')) {
                        $namespace = Objects::getNamespace($this->class);
                        if ($namespace) {
                            $value = $namespace . '\\' . $value;
                        }
                    }
                    break;
            }
        }

        $this->data = $data;
    }

    /**
     * Get data.
     *
     * @return array
     */
    public final function getData(): array
    {
        return $this->data;
    }

    /**
     * Check a data field by given key.
     *
     * @param  string $key
     * @return bool
     */
    public final function hasDataField(string $key): bool
    {
        return array_isset($this->data, $key);
    }

    /**
     * Get a data field by given key.
     *
     * @param  string     $key
     * @param  mixed|null $default
     * @return mixed
     */
    public final function getDataField(string $key, mixed $default = null): mixed
    {
        return array_get($this->data, $key, $default);
    }

    /**
     * Get a data field by given key as a bool option.
     *
     * @param  string     $key
     * @param  mixed|null $default
     * @return bool
     */
    public final function getOption(string $name, mixed $default = null): bool
    {
        return (bool) $this->getDataField($name, $default);
    }

    /**
     * Set reflection.
     *
     * @param  ReflectionClass|ReflectionProperty $reflection
     * @return void
     */
    public final function setReflection(ReflectionClass|ReflectionProperty $reflection): void
    {
        $this->reflection = $reflection;
    }

    /**
     * Get reflection.
     *
     * @return ReflectionClass|ReflectionProperty|null
     */
    public final function getReflection(): ReflectionClass|ReflectionProperty|null
    {
        return $this->reflection ?? null;
    }

    /**
     * Prepare a meta object name.
     *
     * @param  int    $type
     * @param  string $name
     * @param  string $class
     * @return string
     */
    public static function prepareName(int $type, string $name, string $class): string
    {
        [$name, $class] = array_map('trim', [$name, $class]);

        // Fully-qualified name for properties.
        if ($type == self::TYPE_PROPERTY && !str_contains($name, '.')) {
            $name = $class . '.' . $name;
        }

        return $name;
    }
}
