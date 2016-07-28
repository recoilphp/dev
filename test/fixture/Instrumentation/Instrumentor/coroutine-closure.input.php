<?php
/**
 * This case verifies that closures are instrumented.
 */

use Generator as Coroutine;

function () : Coroutine {
    yield 1;
    yield 2;
};
