<?php
/**
 * This case verifies that methods are instrumented.
 */

use Generator as Coroutine;

class X
{
    public static function staticMethod() : Coroutine
    {
        yield 1;
        yield 2;
    }

    public function instanceMethod() : Coroutine
    {
        yield 1;
        yield 2;
    }
}
