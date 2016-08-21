<?php

declare (strict_types = 1); // @codeCoverageIgnore

use Peridot\Runner\Context;
use Recoil\Exception\StrandException;

/**
 * A coroutine-based version of Peridot's it() function, for use in the
 * functional test suite.
 */
function rit(string $description, callable $test)
{
    Context::getInstance()->addTest(
        $description,
        function () use ($test) {
            $result = $test();

            if ($result === null) {
                $strand = null;
            } else {
                $strand = $this->kernel()->execute($result);
            }

            try {
                $this->kernel()->run();
            } catch (StrandException $e) {
                if ($e->strand() === $strand) {
                    throw $e->getPrevious();
                }

                throw $e;
            }

            if ($strand) {
                expect($strand->hasExited())->to->be->true;
            }
        }
    );
}
