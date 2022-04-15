<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
declare(strict_types=1);

namespace froq\database\trait;

use froq\pager\Pager;

/**
 * A trait, provides `$pager` property and its setter/getter methods.
 *
 * @package froq\database\trait
 * @object  froq\database\trait\PagerTrait
 * @author  Kerem Güneş
 * @since   6.0
 */
trait PagerTrait
{
    /** @var ?froq\pager\Pager */
    protected ?Pager $pager = null;

    /**
     * Set pager property.
     *
     * @param  ?froq\pager\Pager $pager
     * @return self
     */
    public final function setPager(?Pager $pager): self
    {
        $this->pager = $pager;

        return $this;
    }

    /**
     * Get pager property.
     *
     * @return ?froq\pager\Pager
     */
    public final function getPager(): ?Pager
    {
        return $this->pager;
    }
}
