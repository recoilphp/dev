<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Peridot;

use Recoil\Exception\StrandException;
use Recoil\Kernel;
use RuntimeException;

final class Executor
{
    public static function create(Kernel $kernel) : self
    {
        return new self($kernel);
    }

    public function execute(callable $test)
    {
        $result = $test();

        if ($result === null) {
            return;
        }

        $strand = $this->kernel->execute($result);

        try {
            $this->kernel->run();
        } catch (StrandException $e) {
            if ($e->strand() === $strand) {
                throw $e->getPrevious();
            }

            throw $e;
        }

        if (!$strand->hasExited()) {
            throw new RuntimeException('Test strand has not exited.');
        }
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
