<?php
/**
 * This case verifies yield is instrumented correctly when used as an expression.
 */

use Generator as Coroutine;

function () : Coroutine {
    $v = yield 1;
    return yield 2;
};
