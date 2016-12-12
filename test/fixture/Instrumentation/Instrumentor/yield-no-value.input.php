<?php
/**
 * This case verifies that yields are instrumented correctly when they do not
 * have a value.
 */

use Generator as Coroutine;

function () : Coroutine {
    yield;
};
