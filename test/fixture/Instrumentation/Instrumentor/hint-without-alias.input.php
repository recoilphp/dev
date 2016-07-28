<?php
/**
 * This case verifies that functions hinted as "Coroutine" where "Coroutine" is
 * NOT an alias for \Generator are not instrumented.
 */

function fn() : Coroutine
{
    yield 1;
    yield 2;
}
