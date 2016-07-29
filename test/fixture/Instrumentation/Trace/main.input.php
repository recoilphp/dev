<?php

namespace Recoil\Dev\Instrumentation;

use Generator as Coroutine;
use RuntimeException;

return function () : Coroutine {
    throw new RuntimeException();
    yield;
};
