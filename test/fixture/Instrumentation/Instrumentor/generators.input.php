<?php
/**
 * This case verifies that regular generators are NOT instrumented.
 */

class Class_
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
};

function func() : Generator
{
    yield;
}

function () : Generator
{
        yield;
};
