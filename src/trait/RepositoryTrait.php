<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\trait;

use froq\database\entity\{Entity, EntityList};

/**
 * A trait, provides a proxy path for calling the methods of `EntityManager`.
 *
 * @package froq\database\trait
 * @class   froq\database\trait\RepositoryTrait
 * @author  Kerem Güneş
 * @since   7.0
 */
trait RepositoryTrait
{
    /** Map of proxy methods. */
    private const METHODS = [
        'save'   => 1, 'saveAll'   => 1,
        'find'   => 1, 'findAll'   => 1, 'findBy'   => 1,
        'remove' => 1, 'removeAll' => 1, 'removeBy' => 1,
    ];

    /**
     * Call a proxy method of `EntityManager` & return its result.
     *
     * Note: All proxy calls must be wrapped in try/catch blocks as they're prone
     * to throw both `ValidationError` and `EntityManagerException`.
     *
     * Note: Return types can be either `Entity`, `EntityList`, array or any object
     * depending on the called method of `EntityManager`.
     *
     * @param  string $method                    Case-sensitive.
     * @param  array  $methodArgs                See original methods.
     * @return array<Entity>|object<Entity>|null Null for safety (future feat).
     * @throws CallError
     */
    public final function __call(string $method, array $methodArgs = []): array|object|null
    {
        if (empty(self::METHODS[$method])) {
            $methods = xarray(self::METHODS)
                ->mapKeys(fn($m) => $m . '()');

            throw new \CallError(format(
                'Invalid call as %S::%s() [valids: %A]',
                $this::class, $method, $methods->keys()
            ));
        }

        return $this->em->$method(...$methodArgs);
    }
}
