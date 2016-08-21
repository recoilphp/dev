<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Peridot;

use Evenement\EventEmitterInterface;
use Peridot\Core\Scope;
use Recoil\Kernel;

class KernelScope extends Scope
{
    public function __construct(
        Kernel $kernel,
        EventEmitterInterface $emitter
    ) {
        $this->kernel = $kernel;
        $this->emitter = $emitter;
    }

    public function kernel() : Kernel
    {
        return $this->kernel;
    }

    private $kernel;
    private $emitter;
}
