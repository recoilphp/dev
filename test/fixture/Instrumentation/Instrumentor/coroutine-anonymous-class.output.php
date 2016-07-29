<?php
/**
 * This case verifies that methods on anonymous classes are instrumented.
 */
use Generator as Coroutine;

new class()
{
    public static function staticMethod() : Coroutine
    {
        assert(!\class_exists(\Recoil\Dev\Instrumentation\Trace::class) || ($μ = yield \Recoil\Dev\Instrumentation\Trace::install()) || true); assert(!isset($μ) || $μ->setCoroutine(__FILE__, __LINE__, __CLASS__, __FUNCTION__, '::', \func_get_args()) || true); yield 1;
        assert(!isset($μ) || $μ->setLine(__LINE__) || true); yield 2;
    }

    public function instanceMethod() : Coroutine
    {
        assert(!\class_exists(\Recoil\Dev\Instrumentation\Trace::class) || ($μ = yield \Recoil\Dev\Instrumentation\Trace::install()) || true); assert(!isset($μ) || $μ->setCoroutine(__FILE__, __LINE__, __CLASS__, __FUNCTION__, '->', \func_get_args()) || true); yield 1;
        assert(!isset($μ) || $μ->setLine(__LINE__) || true); yield 2;
    }
};
