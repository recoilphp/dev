<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Generator;

context('strand', function () {
    it('can invoke generator as coroutine', function () {
        $result = yield (function () {
            yield;

            return '<ok>';
        })();

        expect($result)->to->equal('<ok>');
    });

    it('can invoke generator as coroutine with yield from', function () {
        $result = yield from (function () {
            yield;

            return '<ok>';
        })();

        expect($result)->to->equal('<ok>');
    });

    it('can invoke coroutine provider', function () {
        $result = yield new class() implements CoroutineProvider {
            public function coroutine(): Generator
            {
                return '<ok>';
                yield;
            }
        };

        expect($result)->to->equal('<ok>');
    });

    it('can invoke awaitable provider', function () {
        $result = yield new class() implements AwaitableProvider {
            public function awaitable(): Awaitable
            {
                return new class() implements Awaitable {
                    public function await(Listener $listener)
                    {
                        $listener->send('<ok>');
                    }
                };
            }
        };

        expect($result)->to->equal('<ok>');
    });

    it('can invoke awaitable', function () {
        $result = yield new class() implements Awaitable {
            public function await(Listener $listener)
            {
                $listener->send('<ok>');
            }
        };

        expect($result)->to->equal('<ok>');
    });

    it('prefers await() to awaitable()', function () {
        $result = yield new class() implements AwaitableProvider, Awaitable {
            public function awaitable(): Awaitable
            {
                expect(false)->to->be->ok('awaitable() was called');
            }

            public function await(Listener $listener)
            {
                $listener->send('<ok>');
            }
        };

        expect($result)->to->equal('<ok>');
    });

    it('exception propagates up the call-stack', function () {
        try {
            $fn = function () {
                throw new Exception('<exception>');
                yield;
            };

            yield $fn();
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (Exception $e) {
            expect($e->getMessage())->to->equal('<exception>');
        }
    });

    it('can be awaited by multiple strands', function () {
        $this->kernel()->execute(function () {
            $strand = yield Recoil::strand();

            yield Recoil::execute(function () use ($strand) {
                yield $strand;
                echo 'b';
            });

            yield Recoil::execute(function () use ($strand) {
                yield $strand;
                echo 'c';
            });

            yield;
            yield;
            yield;

            echo 'a';
        });

        ob_start();
        $this->kernel()->run();
        expect(ob_get_clean())->to->equal('abc');
    });
});
