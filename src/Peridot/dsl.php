<?php

declare(strict_types=1); // @codeCoverageIgnore

use Peridot\Runner\Context;
use Recoil\Dev\Peridot\Executor;

/**
 * A coroutine-based version of Peridot's it() function, for use in the
 * functional test suite.
 *
 * @codeCoverageIgnore
 */
function rit(string $description, callable $test)
{
    Context::getInstance()->addTest(
        $description,
        function () use ($test) {
            Executor::execute($this->kernel());
            $executor->execute($test);
        }
    );
}
