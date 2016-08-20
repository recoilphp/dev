<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev;

use Recoil\Exception\StrandException;

/**
 * A coroutine-based version of Peridot's it() function, for use in the
 * functional test suite.
 */
function it(string $description, callable $test)
{
    \it($description, function () use ($test) {
        $result = $test();

        if ($result === null) {
            $strand = null;
        } else {
            $strand = $this->kernel->execute($test);
        }

        try {
            $this->kernel->run();
        } catch (StrandException $e) {
            if ($e->strand() === $strand) {
                throw $e->getPrevious();
            }

            throw $e;
        }

        if ($strand) {
            expect($strand->hasExited())->to->be->true;
        }
    });
}
