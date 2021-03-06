<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Exception\TerminatedException;
use Recoil\Exception\TimeoutException;

context('api/timeout', function () {
    it('throws a timeout exception if the coroutine takes too long', function () {
        try {
            yield Recoil::timeout(
                0.05,
                function () {
                    yield 0.1;
                }
            );
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (TimeoutException $e) {
            // ok ...
        }
    });

    it('returns value if the coroutine returns before the timeout', function () {
        $result = yield Recoil::timeout(
            1,
            function () {
                return '<ok>';
                yield;
            }
        );

        expect($result)->to->equal('<ok>');
    });

    it('propagates exception if the coroutine throws before the timeout', function () {
        try {
            yield Recoil::timeout(
                1,
                function () {
                    throw new Exception('<exception>');
                    yield;
                }
            );
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (Exception $e) {
            expect($e->getMessage())->to->equal('<exception>');
        }
    });

    it('propagates exception if the coroutine is terminated before the timeout', function () {
        try {
            yield Recoil::timeout(
                1,
                function () {
                    yield Recoil::terminate();
                }
            );
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (TerminatedException $e) {
            // ok ...
        }
    });

    it('terminates the substrand if the calling strand is terminated', function () {
        $strand = yield Recoil::execute(function () {
            yield (function () {
                yield Recoil::timeout(
                    0.02,
                    function () {
                        yield 0.01;
                        expect(false)->to->be->ok('strand was not terminated');
                    }
                );
            })();
        });

        yield;

        $strand->terminate();
    });
});
