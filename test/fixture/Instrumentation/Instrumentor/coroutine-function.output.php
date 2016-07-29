<?php
/**
 * This case verifies that functions are instrumented.
 */

use Generator as Coroutine;

function fn() : Coroutine
{
    assert((($μ = \class_exists(\Recoil\Dev\Instrumentation\Trace::class) ? yield \Recoil\Dev\Instrumentation\Trace::install() : null) && $μ->setCoroutine(__FILE__, __LINE__, __CLASS__, __FUNCTION__, '', \func_get_args())) || true); yield 1;
    assert(($μ && $μ->setLine(__LINE__)) || true); yield 2;
}
