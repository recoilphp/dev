<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use UnexpectedValueException;

context('api/callable', function () {
    context('when it returns a generator', function () {
        it('invokes the generator as a coroutine', function () {
            $fn = function () {
                return '<result>';
                yield;
            };

            expect(yield $fn)->to->equal('<result>');
        });
    });

    context('when it returns a non-generator', function () {
        it('resumes the calling strand with an exception', function () {
            $fn = function () {
                return '<string>';
            };

            try {
                yield $fn;
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (UnexpectedValueException $e) {
                // ok
            }
        });
    });
});
