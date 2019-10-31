<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Peridot;

use Evenement\EventEmitterInterface;
use Recoil\Exception\StrandException;
use Recoil\Kernel;
use RuntimeException;

/**
 * A Peridot plugin that executes all test/setup functions as coroutines.
 */
final class Plugin
{
    /**
     * Install the plugin.
     *
     * @param EventEmitterInterface $emitter Peridot's event emitter.
     * @param callable              $factory A function that returns a Kernel instance.
     */
    public static function install(
        EventEmitterInterface $emitter,
        callable $factory
    ) {
        $scope = new Scope();

        $emitter->on('peridot.configure', function ($config) {
            $config->setDsl(__DIR__ . '/dsl.php');
        });

        $emitter->on('suite.start', function ($test) use ($scope) {
            $test->getScope()->peridotAddChildScope($scope);
        });

        $emitter->on('test.start', function () use ($scope, $factory) {
            $scope->setKernel($factory());
        });
    }

    /**
     * Please note that this code is not part of the public API. It may be
     * changed or removed at any time without notice.
     *
     * @access private
     */
    public static function wrap(callable $fn): callable
    {
        return function () use ($fn) {
            $fn = $this->peridotBindTo($fn);
            $result = $fn();

            if ($result === null) {
                return;
            }

            $kernel = $this->kernel();
            $strand = $kernel->execute($result);

            try {
                $kernel->run();
            } catch (StrandException $e) {
                if ($e->strand() === $strand) {
                    throw $e->getPrevious();
                }

                throw $e;
            }

            if (!$strand->hasExited()) {
                throw new RuntimeException('Test strand has not exited.');
            }
        };
    }
}
