<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\entity\proxy;

/**
 * A trait, provides `$proxy` property and its getter method to entity & entity list classes.
 *
 * @package froq\database\entity\proxy
 * @object  froq\database\entity\proxy\ProxyTrait
 * @author  Kerem Güneş
 * @since   6.0
 * @internal
 */
trait ProxyTrait
{
    /** @var ?froq\database\entity\proxy\Proxy */
    private ?Proxy $proxy = null;

    /**
     * Get proxy property.
     *
     * @return ?froq\database\entity\proxy\Proxy
     */
    public final function proxy(): ?Proxy
    {
        return $this->proxy;
    }
}
