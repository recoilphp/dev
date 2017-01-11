<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Peridot;

use Peridot\Core\Scope as BaseScope;
use Recoil\Kernel;

/**
 * A Peridot scope that makes a Recoil kernel available.
 */
final class Scope extends BaseScope
{
    /**
     * Get the kernel.
     */
    public function kernel() : Kernel
    {
        return $this->kernel;
    }

    /**
     * Set the kernel.
     */
    public function setKernel(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @var Kernel|null The kernel.
     */
    private $kernel;
}
