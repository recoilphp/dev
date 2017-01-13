<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Eloquent\Phony\Phony;
use Exception;
use Hamcrest\Core\IsInstanceOf;
use Recoil\Exception\TerminatedException;

context('kernel/strand-trace', function () {
    beforeEach(function () {
        $this->trace = Phony::mock(StrandTrace::class);
    });

    if (ini_get('zend.assertions') > 0) {
        context('when assertions are enabled', function () {
            describe('->trace()', function () {
                it('returns the trace object', function () {
                    $strand = yield Recoil::execute(function () {
                        yield;
                    });

                    expect($strand->trace())->to->be->null;

                    $strand->setTrace($this->trace->get());

                    expect($strand->trace())->to->be->equal($this->trace->get());
                });
            });

            describe('->setTrace()', function () {
                it('sets the trace object', function () {
                    $strand = yield Recoil::execute(function () {
                        yield;
                    });

                    $strand->setTrace($this->trace->get());

                    expect($strand->trace())->to->equal($this->trace->get());
                });

                it('can set the trace object to null', function () {
                    $strand = yield Recoil::execute(function () {
                        yield;
                    });

                    $strand->setTrace(null);

                    expect($strand->trace())->to->be->null;
                });
            });

            context('when the strand is tracing', function () {
                it('traces the events in order', function () {
                    $generator = (function () {
                        return 100;
                        yield;
                    })();

                    $strand = yield Recoil::execute((function () use ($generator) {
                        return (yield '<key>' => $generator) + 200;
                    })());

                    $strand->setTrace($this->trace->get());
                    yield $strand;

                    Phony::inOrder(
                        $this->trace->push->calledWith(
                            $strand,
                            0 // call-stack depth
                        ),
                        $this->trace->yield->calledWith(
                            $strand,
                            1, // call-stack depth
                            '<key>',
                            $generator
                        ),
                        $this->trace->push->calledWith(
                            $strand,
                            1 // call-stack depth
                        ),
                        $this->trace->pop->calledWith(
                            $strand,
                            1 // call-stack depth
                        ),
                        $this->trace->resume->calledWith(
                            $strand,
                            1, // call-stack depth
                            'send',
                            100
                        ),
                        $this->trace->pop->calledWith(
                            $strand,
                            0 // call-stack depth
                        ),
                        $this->trace->exit->calledWith(
                            $strand,
                            0, // call-stack depth
                            'send',
                            300
                        )
                    );

                    $this->trace->push->twice()->called();
                    $this->trace->pop->twice()->called();
                    $this->trace->yield->once()->called();
                    $this->trace->resume->once()->called();
                    $this->trace->suspend->never()->called();
                    $this->trace->exit->once()->called();
                });

                it('traces exit values', function () {
                    $strand = yield Recoil::execute(function () {
                        return '<value>';
                        yield;
                    });

                    $strand->setTrace($this->trace->get());
                    yield $strand;

                    $this->trace->exit->calledWith(
                        $strand,
                        0, // call-stack depth
                        'send',
                        '<value>'
                    );
                });

                it('traces exit exception', function () {
                    $strand = yield Recoil::execute(function () {
                        throw new Exception();
                        yield;
                    });

                    $strand->setTrace($this->trace->get());

                    try {
                        yield Recoil::adopt($strand);
                        expect(false)->to->be->ok('expected exception was not thrown');
                    } catch (Exception $e) {
                        $this->trace->exit->calledWith(
                            $strand,
                            0, // call-stack depth
                            'throw',
                            $e
                        );
                    }
                });

                it('traces strand suspension', function () {
                    $strand = yield Recoil::execute((function () {
                        yield Recoil::suspend();
                    })());

                    $strand->setTrace($this->trace->get());
                    yield;

                    Phony::inOrder(
                        $this->trace->push->calledWith(
                            $strand,
                            0 // call-stack depth
                        ),
                        $this->trace->yield->calledWith(
                            $strand,
                            1, // call-stack depth
                            0,
                            Recoil::suspend()
                        ),
                        $this->trace->suspend->calledWith(
                            $strand,
                            1 // call-stack depth
                        )
                    );

                    $this->trace->push->once()->called();
                    $this->trace->pop->never()->called();
                    $this->trace->yield->once()->called();
                    $this->trace->resume->never()->called();
                    $this->trace->suspend->once()->called();
                    $this->trace->exit->never()->called();
                });

                it('traces strand termination', function () {
                    $strand = yield Recoil::execute((function () {
                        yield;
                    })());

                    $strand->setTrace($this->trace->get());
                    $strand->terminate();

                    $this->trace->exit->calledWith(
                        $strand,
                        1, // call-stack depth
                        'throw',
                        IsInstanceOf::anInstanceOf(TerminatedException::class)
                    );

                    $this->trace->push->never()->called();
                    $this->trace->pop->never()->called();
                    $this->trace->yield->never()->called();
                    $this->trace->resume->never()->called();
                    $this->trace->suspend->never()->called();
                    $this->trace->exit->once()->called();
                });
            });
        });
    } else {
        context('when assertions are disabled', function () {
            describe('->trace()', function () {
                it('always returns null', function () {
                    $strand = yield Recoil::execute(function () {
                        yield;
                    });

                    expect($strand->trace())->to->be->null;
                });
            });

            describe('->setTrace()', function () {
                it('does not set the trace object', function () {
                    $strand = yield Recoil::execute(function () {
                        yield;
                    });

                    $strand->setTrace($this->trace->get());

                    expect($strand->trace())->to->be->null;
                });
            });
        });
    }
});
