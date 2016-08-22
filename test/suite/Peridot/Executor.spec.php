<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Peridot;

use Eloquent\Phony\Phony;
use Exception;
use Peridot\Core\Test;
use Recoil\Exception\StrandException;
use Recoil\Kernel;
use Recoil\Strand;
use RuntimeException;

describe(Executor::class, function () {
    beforeEach(function () {
        $this->strand = Phony::mock(Strand::class);
        $this->strand->hasExited->returns(true);
        $this->kernel = Phony::mock(Kernel::class);
        $this->kernel->execute->returns($this->strand);
        $this->subject = Executor::create($this->kernel->get());
        $this->test = Phony::stub();
    });

    describe('->execute()', function () {
        it('runs the test', function () {
            $this->subject->execute($this->test);
            $this->test->calledWith();
        });

        it('executes non-null return values', function () {
            $this->test->returns('<value>');
            $this->subject->execute($this->test);

            $this->test->calledWith();

            Phony::inOrder(
                $this->kernel->execute->calledWith('<value>'),
                $this->kernel->run->called()
            );
        });

        it('does not execute null return values', function () {
            $this->subject->execute($this->test);

            $this->test->calledWith();
            $this->kernel->noInteraction();
        });

        it('fails if the test strand does not exit', function () {
            $this->test->returns('<value>');
            $this->strand->hasExited->returns(false);

            try {
                $this->subject->execute($this->test);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (RuntimeException $e) {
                expect($e->getMessage())->to->equal('Test strand has not exited.');
            }
        });

        it('unwraps strand exceptions for the test strand', function () {
            $previous = new Exception('<exception>');
            $exception = new StrandException($this->strand->get(), $previous);

            $this->kernel->run->throws($exception);
            $this->test->returns('<value>');

            try {
                $this->subject->execute($this->test);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (Exception $e) {
                expect($e === $previous)->to->be->true;
            }
        });

        it('does not unwrap strand exceptions for other strands', function () {
            $strand = Phony::mock(Strand::class)->get();
            $previous = new Exception('<exception>');
            $exception = new StrandException($strand, $previous);

            $this->kernel->run->throws($exception);
            $this->test->returns('<value>');

            try {
                $this->subject->execute($this->test);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (Exception $e) {
                expect($e === $exception)->to->be->true;
            }
        });
    });
});
