<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Peridot;

use Eloquent\Phony\Phony;
use Evenement\EventEmitterInterface;
use Exception;
use Peridot\Configuration;
use Peridot\Console\Application;
use Peridot\Core\Suite;
use Peridot\Core\Test;
use Recoil\Exception\StrandException;
use Recoil\Kernel;
use Recoil\Strand;
use RuntimeException;

describe(Plugin::class, function () {
    describe('::install()', function () {
        beforeEach(function () {
            $this->emitter = Phony::mock(EventEmitterInterface::class);
            $this->app = Phony::mock(Application::class);
            $this->config = Phony::mock(Configuration::class);
            $this->suite = new Suite('description', function () {
            });
            $this->test = new Test('description');
            $this->kernel1 = Phony::mock(Kernel::class);
            $this->kernel2 = Phony::mock(Kernel::class);
            $this->factory = Phony::stub();
            $this->factory->returns($this->kernel1, $this->kernel2);
        });

        it('sets the DSL', function () {
            Plugin::install($this->emitter->get(), $this->factory);

            $fn = $this->emitter->on
                ->calledWith('peridot.configure', '~')
                ->firstCall()
                ->argument(1);

            $fn($this->config->get(), $this->app->get());

            $file = $this->config->setDsl->calledWith('~')->firstCall()->argument();
            $file = realpath($file);

            expect($file)->to->equal(
                realpath(__DIR__ . '/../../../src/Peridot/dsl.php')
            );
        });

        it('adds the scope when a suite starts', function () {
            Plugin::install($this->emitter->get(), $this->factory);

            $fn = $this->emitter->on
                ->calledWith('suite.start', '~')
                ->firstCall()
                ->argument(1);

            $fn($this->suite);

            foreach ($this->suite->getScope()->peridotGetChildScopes() as $scope) {
                if ($scope instanceof Scope) {
                    return;
                }
            }

            expect(false)->to->be->ok('no kernel scope was added');
        });

        it('sets a new kernel when a test starts', function () {
            Plugin::install($this->emitter->get(), $this->factory);

            $fn = $this->emitter->on
                ->calledWith('suite.start', '~')
                ->firstCall()
                ->argument(1);

            $fn($this->suite);

            $fn = $this->emitter->on
                ->calledWith('test.start', '~')
                ->firstCall()
                ->argument(1);

            foreach ($this->suite->getScope()->peridotGetChildScopes() as $scope) {
                if ($scope instanceof Scope) {
                    $fn($this->test);
                    expect($scope->kernel())->to->equal($this->kernel1->get());

                    $fn($this->test);
                    expect($scope->kernel())->to->equal($this->kernel2->get());

                    return;
                }
            }
        });
    });

    describe('::wrap()', function () {
        beforeEach(function () {
            $this->strand = Phony::mock(Strand::class);
            $this->strand->hasExited->returns(true);
            $this->kernel = Phony::mock(Kernel::class);
            $this->kernel->execute->returns($this->strand);
            $this->test = Phony::stub();
            $this->scope = new Scope();
            $this->scope->setKernel($this->kernel->get());

            $fn = Plugin::wrap($this->test);
            $this->subject = $fn->bindTo($this->scope, $this->scope);
        });

        it('runs the test', function () {
            ($this->subject)();
            $this->test->calledWith();
        });

        it('executes non-null return values', function () {
            $this->test->returns('<value>');
            ($this->subject)();

            $this->test->calledWith();

            Phony::inOrder(
                $this->kernel->execute->calledWith('<value>'),
                $this->kernel->run->called()
            );
        });

        it('does not execute null return values', function () {
            ($this->subject)();

            $this->test->calledWith();
            $this->kernel->noInteraction();
        });

        it('fails if the test strand does not exit', function () {
            $this->test->returns('<value>');
            $this->strand->hasExited->returns(false);

            try {
                ($this->subject)();
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
                ($this->subject)();
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
                ($this->subject)();
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (Exception $e) {
                expect($e === $exception)->to->be->true;
            }
        });

        it('binds to the scope', function () {
            $self = null;
            $fn = Plugin::wrap(function () use (&$self) {
                $self = $this;
            });

            $this->subject = $fn->bindTo($this->scope, $this->scope);
            ($this->subject)();

            expect($self)->to->equal($this->scope);
        });
    });
});
