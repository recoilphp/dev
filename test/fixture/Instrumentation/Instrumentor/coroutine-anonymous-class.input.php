<?php
/**
 * This case verifies that methods on anonymous classes are instrumented.
 */
use Generator as Coroutine;

new class()
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
};
