<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Peridot;

use Evenement\EventEmitterInterface;
use Peridot\Core\Scope as BaseScope;
use Recoil\Kernel;

/**
 * A Peridot scope that makes a Recoil kernel available.
 */
final class Scope extends BaseScope
{
    /**
     * Create a new scope and bind it to the peridot event emitter.
     *
     * @param EventEmitterInterface $emitter Peridot's event emitter.
     * @param callable              $factory A function that returns a Kernel instance.
     *
     * @return KernelScope
     */
    public static function install(
        EventEmitterInterface $emitter,
        callable $factory
    ) {
        $scope = new self($factory());

        $emitter->on('suite.start', function ($test) use ($scope) {
            $test->getScope()->peridotAddChildScope($scope);
        });
    }

    /**
     * Get the kernel.
     */
    public function kernel() : Kernel
    {
        return $this->kernel;
    }

    /**
     * @access private
     *
     * @param Kernel $kernel The kernel to expose in the scope.
     */
    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    private $kernel;
}
