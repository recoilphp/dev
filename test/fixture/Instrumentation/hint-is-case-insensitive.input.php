<?php
/**
 * This case verifies that type-hint resolution is case-insensitive.
 */

use generator as Coroutine;

function fn() : coroutine
{
    yield 1;
    yield 2;
}
