<?php
/**
 * This case verifies that methods are instrumented.
 */

use Generator as Coroutine;

class X
{
    public static function staticMethod() : Coroutine
    {
        assert((($μ = \class_exists(\Recoil\Dev\Instrumentation\Trace::class) ? yield \Recoil\Dev\Instrumentation\Trace::install() : null) && $μ->setCoroutine(__FILE__, __CLASS__, __FUNCTION__, '::', \func_get_args())) || true); (!assert(($μ && $μ->setLine(__LINE__)) || true) ?: yield 1);
        (!assert(($μ && $μ->setLine(__LINE__)) || true) ?: yield 2);
    }

    public function instanceMethod() : Coroutine
    {
        assert((($μ = \class_exists(\Recoil\Dev\Instrumentation\Trace::class) ? yield \Recoil\Dev\Instrumentation\Trace::install() : null) && $μ->setCoroutine(__FILE__, __CLASS__, __FUNCTION__, '->', \func_get_args())) || true); (!assert(($μ && $μ->setLine(__LINE__)) || true) ?: yield 1);
        (!assert(($μ && $μ->setLine(__LINE__)) || true) ?: yield 2);
    }
}
