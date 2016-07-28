<?php
/**
 * This case verifies that closures are instrumented.
 */

use Generator as Coroutine;

function () {
    function () : Coroutine {
        yield 1;
        yield 2;
    };
};
