<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation;

use Eloquent\Phony\Phony;
use Recoil\Kernel\StrandTrace;
use Recoil\Recoil;

describe(TraceInstaller::class, function () {

    if (ini_get('zend.assertions') > 0) {
        it('installs an instrumentation trace', function () {
            yield new TraceInstaller();
            $strand = yield Recoil::strand();

            expect($strand->trace())->to->be->an->instanceof(Trace::class);
        });

        it('does not replace an existing trace', function () {
            $trace = Phony::mock(StrandTrace::class)->get();
            $strand = yield Recoil::strand();
            $strand->setTrace($trace);

            yield new TraceInstaller();

            expect($strand->trace())->to->equal($trace);
        });

        it('returns a newly installed instrumentation trace', function () {
            $trace = yield new TraceInstaller();
            $strand = yield Recoil::strand();

            expect($strand->trace())->to->equal($trace);
        });

        it('returns a previously installed instrumentation trace', function () {
            $trace = yield new TraceInstaller();

            expect(yield new TraceInstaller())->to->equal($trace);
        });

        it('does not replace an existing third-party trace', function () {
            $trace = Phony::mock(StrandTrace::class)->get();
            $strand = yield Recoil::strand();
            $strand->setTrace($trace);

            yield new TraceInstaller();

            expect($strand->trace())->to->equal($trace);
        });

        it('returns null when there is an existing third-party trace', function () {
            $trace = Phony::mock(StrandTrace::class)->get();
            $strand = yield Recoil::strand();
            $strand->setTrace($trace);

            expect(yield new TraceInstaller())->to->be->null;
        });
    }

});
