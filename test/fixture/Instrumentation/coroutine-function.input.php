<?php
/**
 * This case verifies that functions are instrumented.
 */

use Generator as Coroutine;

function fn() : Coroutine
{
    yield 1;
    yield 2;
}
