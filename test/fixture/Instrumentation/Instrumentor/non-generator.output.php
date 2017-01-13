<?php
/**
 * This case verifies that functions hinted as "Coroutine" that are not
 * generators do not have tracing code inserted.
 */

use Generator as Coroutine;

function fn() : Coroutine
{
    return other();
}
