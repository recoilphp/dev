<?php
/**
 * This case verifies that closures are instrumented when nested in a function.
 */

use Generator as Coroutine;

function () : Coroutine {
    function () : Coroutine {
        yield 1;
        yield 2;
    };

    yield 3;
};
