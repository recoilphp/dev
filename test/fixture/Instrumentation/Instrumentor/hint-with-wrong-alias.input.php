<?php
/**
 * This case verifies that functions hinted as "Coroutine" where "Coroutine" is
 * an alias for something other than \Generator are not instrumented.
 */

use Traversable as Coroutine;

function func() : Coroutine
{
    yield 1;
    yield 2;
}
