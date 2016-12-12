<?php
/**
 * This case verifies that yields are instrumented correctly when they have a key.
 */

use Generator as Coroutine;

function () : Coroutine {
    yield 1 => 2;
};
