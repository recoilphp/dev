<?php
/**
 * This case verifies that functions hinted as "Coroutine" where "Coroutine" is
 * NOT an alias for \Generator are not instrumented.
 */

use Iterator as Coroutine;

function func() : Coroutine
{
    yield 1;
    yield 2;
}
