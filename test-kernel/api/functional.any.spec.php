<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Exception\CompositeException;
use Recoil\Exception\TerminatedException;

context('api/any', function () {
    it('executes the coroutines', function () {
        ob_start();
        yield Recoil::any(
            function () {
                echo 'a';
                yield;
            },
            function () {
                echo 'b';

                return;
                yield;
            }
        );
        expect(ob_get_clean())->to->equal('ab');
    });

    it('terminates the substrands when the calling strand is terminated', function () {
        $strand = yield Recoil::execute(function () {
            yield (function () {
                yield Recoil::any(
                    function () {
                        yield;
                        expect(false)->to->be->ok('strand was not terminated');
                    },
                    function () {
                        yield;
                        expect(false)->to->be->ok('strand was not terminated');
                    }
                );
            })();
        });

        yield;

        $strand->terminate();
    });

    context('when one of the substrands succeeds', function () {
        it('returns the coroutine return value', function () {
            expect(yield Recoil::any(
                function () {
                    yield;

                    return 'a';
                },
                function () {
                    return 'b';
                    yield;
                }
            ))->to->equal('b');
        });

        it('terminates the remaining strands', function () {
            yield Recoil::any(
                function () {
                    yield;
                    expect(false)->to->be->ok('strand was not terminated');
                },
                function () {
                    return;
                    yield;
                }
            );
        });
    });

    context('when all of the substrands fail or are terminated', function () {
        it('throws a composite exception', function () {
            try {
                yield Recoil::any(
                    function () {
                        yield Recoil::terminate();
                    },
                    function () {
                        throw new Exception('<exception>');
                        yield;
                    }
                );
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (CompositeException $e) {
                expect($e->exceptions())->to->have->length(2);
                expect($e->exceptions()[0])->to->be->an->instanceof(TerminatedException::class);
                expect($e->exceptions()[1])->to->be->an->instanceof(Exception::class);
            }
        });

        it('sorts the previous exceptions based on the order that the substrands exit', function () {
            try {
                yield Recoil::any(
                    function () {
                        yield;
                        yield;
                        throw new Exception('<exception-a>');
                    },
                    function () {
                        yield;
                        throw new Exception('<exception-b>');
                    }
                );
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (CompositeException $e) {
                expect(array_keys($e->exceptions()))->to->equal([1, 0]);
            }
        });
    });

    context('when no coroutines are provided', function () {
        it('yields control to another strand', function () {
            ob_start();

            yield Recoil::execute(function () {
                echo 'b';

                return;
                yield;
            });

            echo 'a';
            yield Recoil::any();
            echo 'c';

            expect(ob_get_clean())->to->equal('abc');
        });

        it('returns null', function () {
            expect(yield Recoil::any())->to->be->null();
        });
    });
});
