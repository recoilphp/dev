<?php

declare (strict_types = 1);

namespace Recoil\Dev\Examples;

use Generator as Coroutine;
use Recoil\React\ReactKernel;

function outer(int $value) : Coroutine
{
    yield middle($value + 1);
}

function middle(int $value) : Coroutine
{
    yield 0.25;
    yield inner($value + 1);
}

function inner(int $value) : Coroutine
{
    yield from failer($value + 1);
}

function failer(int $value) : Coroutine
{
    yield;
    fail($value + 1);
}

function fail(int $value)
{
    throw new \Exception('<OH SHIT>');
}

ReactKernel::start(outer(100));
