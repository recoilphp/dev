<?php
/**
 * This case verifies that type-hint resolution is case-insensitive.
 */

use generator as Coroutine;

function func() : coroutine
{
    yield 1;
    yield 2;
}
