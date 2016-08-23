<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation;

use Eloquent\Phony\Phony;
use Recoil\Dev\Instrumentation\Autoloader\StreamWrapper;
use Recoil\Recoil;
use Recoil\StrandTrace;
use RuntimeException;

describe(Trace::class, function () {
    $context = ini_get('zend.assertions') > 0 ? 'context' : 'xcontext';
    $context('when assertions are enabled', function () {
        describe('::install()', function () {
            rit('installs an instrumentation trace', function () {
                yield Trace::install();
                $strand = yield Recoil::strand();

                expect($strand->trace())->to->be->an->instanceof(Trace::class);
            });

            rit('does not replace an existing trace', function () {
                $trace = Phony::mock(StrandTrace::class)->get();
                $strand = yield Recoil::strand();
                $strand->setTrace($trace);

                yield Trace::install();

                expect($strand->trace())->to->equal($trace);
            });

            rit('returns a newly installed instrumentation trace', function () {
                $trace = yield Trace::install();
                $strand = yield Recoil::strand();

                expect($strand->trace())->to->equal($trace);
            });

            rit('returns a previously installed instrumentation trace', function () {
                $trace = yield Trace::install();

                expect(yield Trace::install())->to->equal($trace);
            });

            rit('does not replace an existing third-party trace', function () {
                $trace = Phony::mock(StrandTrace::class)->get();
                $strand = yield Recoil::strand();
                $strand->setTrace($trace);

                yield Trace::install();

                expect($strand->trace())->to->equal($trace);
            });

            rit('returns null when there is an existing third-party trace', function () {
                $trace = Phony::mock(StrandTrace::class)->get();
                $strand = yield Recoil::strand();
                $strand->setTrace($trace);

                expect(yield Trace::install())->to->be->null;
            });
        });

        context('the stack trace is rewritten', function () {
            $fixtures = glob(__DIR__ . '/../../fixture/Instrumentation/Trace/*.input.php');
            $scheme = StreamWrapper::install(Instrumentor::create());

            foreach ($fixtures as $path) {
                rit(explode('.', basename($path))[0], function () use ($scheme, $path) {
                    $fn = require $scheme . '://' . $path;

                    try {
                        $strand = yield Recoil::execute($fn());
                        yield Recoil::adopt($strand);
                        expect(false)->to->be->ok('exception exception was not thrown');
                    } catch (RuntimeException $e) {
                        $expected = str_replace(
                            '__FILE__',
                            realpath($path),
                            file_get_contents(
                                str_replace('input.php', 'output.txt', $path)
                            )
                        );

                        // Use a phony spy for the expection so that we get nice diff output ...
                        $spy = Phony::spy();
                        $spy($e->getTraceAsString());
                        $spy->calledWith(trim($expected));
                    }
                });
            }
        });
    });
});
