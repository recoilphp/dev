<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Exception\TerminatedException;

context('api/first', function () {
    it('executes the coroutines', function () {
        ob_start();
        yield Recoil::first(
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
                yield Recoil::first(
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
            expect(yield Recoil::first(
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
            yield Recoil::first(
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

    context('when one of the substrands fails', function () {
        it('propagates the exception', function () {
            try {
                yield Recoil::first(
                    function () {
                        yield;
                    },
                    function () {
                        throw new Exception('<exception>');
                        yield;
                    }
                );
            } catch (Exception $e) {
                expect($e->getMessage())->to->equal('<exception>');
            }
        });

        it('terminates the remaining strands', function () {
            try {
                yield Recoil::first(
                    function () {
                        yield;
                        expect(false)->to->be->ok('strand was not terminated');
                    },
                    function () {
                        throw new Exception('<exception>');
                        yield;
                    }
                );
            } catch (Exception $e) {
                // ok ...
            }
        });
    });

    context('when one of the substrands is terminated', function () {
        it('throws an exception', function () {
            $id = null;
            try {
                yield Recoil::first(
                    function () {
                        yield;
                        yield;
                    },
                    function () use (&$id) {
                        $id = (yield Recoil::strand())->id();
                        yield Recoil::terminate();
                    }
                );
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (TerminatedException $e) {
                expect($e->getMessage())->to->equal("Strand #$id was terminated.");
            }
        });

        it('terminates the remaining strands', function () {
            try {
                yield Recoil::first(
                    function () {
                        yield;
                        expect(false)->to->be->ok('strand was not terminated');
                    },
                    function () {
                        throw new Exception('<exception>');
                        yield;
                    }
                );
            } catch (Exception $e) {
                // ok ...
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
            yield Recoil::first();
            echo 'c';

            expect(ob_get_clean())->to->equal('abc');
        });

        it('returns null', function () {
            expect(yield Recoil::first())->to->be->null();
        });
    });
});
