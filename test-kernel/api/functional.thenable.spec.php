<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Eloquent\Phony\Phony;
use Exception;

context('api/thenable', function () {
    context('when it has a then method', function () {
        it('resumes the strand when the promise is resolved', function () {
            $promise = Phony::partialMock(
                [
                    'then' => function (callable $resolve, callable $reject) {
                        $resolve('<value>');
                    },
                ]
            );

            expect(yield $promise->get())->to->equal('<value>');
        });

        it('resumes the strand with an exception when the promise is rejected', function () {
            $promise = Phony::partialMock(
                [
                    'then' => function (callable $resolve, callable $reject) {
                        $reject(new \Exception('<rejected>'));
                    },
                ]
            );

            try {
                yield $promise->get();
                expect(false)->to->equal('Expected exception was not thrown.');
            } catch (Exception $e) {
                expect($e->getMessage())->to->equal('<rejected>');
            }
        });

        it('resumes the strand with an exception when the promise is rejected with a non-exception', function () {
            $promise = Phony::partialMock(
                [
                    'then' => function (callable $resolve, callable $reject) {
                        $reject('<rejected>');
                    },
                ]
            );

            try {
                yield $promise->get();
                expect(false)->to->equal('Expected exception was not thrown.');
            } catch (Exception $e) {
                expect($e->getMessage())->to->equal('<rejected>');
            }
        });

        it('cancels the promise when the strand is terminated', function () {
            $promise = Phony::partialMock(
                [
                    'then' => function (callable $resolve, callable $reject) {
                    },
                    'cancel' => function () {
                    },
                ]
            );

            $strand = yield Recoil::execute(function () use ($promise) {
                yield $promise->get();
            });

            yield;

            $strand->terminate();

            $promise->cancel->called();
        });
    });

    context('when it has both then and done methods', function () {
        it('resumes the strand when the promise is resolved', function () {
            $promise = Phony::partialMock(
                [
                    'then' => function (callable $resolve, callable $reject) {
                    },
                    'done' => function (callable $resolve, callable $reject) {
                        $resolve('<value>');
                    },
                ]
            );

            expect(yield $promise->get())->to->equal('<value>');
        });

        it('resumes the strand with an exception when the promise is rejected', function () {
            $promise = Phony::partialMock(
                [
                    'then' => function (callable $resolve, callable $reject) {
                    },
                    'done' => function (callable $resolve, callable $reject) {
                        $reject(new \Exception('<rejected>'));
                    },
                ]
            );

            try {
                yield $promise->get();
                expect(false)->to->equal('Expected exception was not thrown.');
            } catch (Exception $e) {
                expect($e->getMessage())->to->equal('<rejected>');
            }
        });

        it('resumes the strand with an exception when the promise is rejected with a non-exception', function () {
            $promise = Phony::partialMock(
                [
                    'then' => function (callable $resolve, callable $reject) {
                    },
                    'done' => function (callable $resolve, callable $reject) {
                        $reject('<rejected>');
                    },
                ]
            );

            try {
                yield $promise->get();
                expect(false)->to->equal('Expected exception was not thrown.');
            } catch (Exception $e) {
                expect($e->getMessage())->to->equal('<rejected>');
            }
        });

        it('cancels the promise when the strand is terminated', function () {
            $promise = Phony::partialMock(
                [
                    'then' => function (callable $resolve, callable $reject) {
                    },
                    'done' => function (callable $resolve, callable $reject) {
                    },
                    'cancel' => function () {
                    },
                ]
            );

            $strand = yield Recoil::execute(function () use ($promise) {
                yield $promise->get();
            });

            yield;

            $strand->terminate();

            $promise->cancel->called();
        });
    });
});
