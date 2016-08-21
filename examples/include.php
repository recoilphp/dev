<?php

declare(strict_types=1);

namespace Recoil\Dev\Examples;

use Generator as Coroutine;
use Recoil\React\ReactKernel;

function outer(int $value) : Coroutine
{
    $closure = function ($value) : Coroutine {
        yield middle($value + 1);
    };

    yield $closure($value + 1);
}

function middle(int $value) : Coroutine
{
    yield 0.25;
    yield Fail::inner($value + 1);
}

class Fail
{
    public static function inner(int $value) : Coroutine
    {
        $closure = function ($value) : Coroutine {
            yield Fail::failer($value + 1);
        };

        yield $closure($value + 1);
    }

    public static function failer(int $value) : Coroutine
    {
        yield;
        self::fail($value + 1);
    }

    public static function fail(int $value)
    {
        throw new \Exception('<OH SHIT>');
    }
}

ReactKernel::start(outer(100));
