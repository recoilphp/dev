<?php
/**
 * This case verifies that regular generators are NOT instrumented.
 */

class Class
{
    public static function staticMethod() : Generator
    {
        yield;
    }

    public function instanceMethod() : Generator
    {
        yield;
    }
}

new class
{
    public static function staticMethod() : Generator
    {
        yield;
    }

    public function instanceMethod() : Generator
    {
        yield;
    }
}

function fn() : Generator
{
    yield;
}

function () : Generator
{
        yield;
};
