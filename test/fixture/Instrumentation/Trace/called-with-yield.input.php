<?php

namespace Recoil\Dev\Instrumentation;

use Generator as Coroutine;
use RuntimeException;

function calledWithYield()
{
    throw new RuntimeException();
    yield;
}

return function () : Coroutine {
    yield calledWithYield(1, 2, 3);
};
