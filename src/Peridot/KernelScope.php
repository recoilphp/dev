<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Peridot;

use Evenement\EventEmitterInterface;
use Peridot\Core\Scope;
use Recoil\Kernel;

class KernelScope extends Scope
{
    public function create(
        EventEmitterInterface $emitter,
        callable $factory
    ) : self {
        return new self($emitter, $factory);
    }

    public function kernel() : Kernel
    {
        return $this->kernel;
    }

    private function __construct(EventEmitterInterface $emitter, callable $factory)
    {
        $this->factory = $factory;

        $emitter->on('suite.start', function ($test) {
            $test->getScope()->peridotAddChildScope($this);
        });
    }

    private $factory;
    private $kernel;
}
