<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Peridot;

use Eloquent\Phony\Phony;
use Evenement\EventEmitterInterface;
use Peridot\Core\Suite;
use Peridot\Core\Test;
use Recoil\Kernel;

describe(Scope::class, function () {
    beforeEach(function () {
        $this->emitter = Phony::mock(EventEmitterInterface::class);
        $this->suite = new Suite('description', function () {
        });
        $this->test = new Test('description');
        $this->kernel1 = Phony::mock(Kernel::class);
        $this->kernel2 = Phony::mock(Kernel::class);
        $this->factory = Phony::stub();
        $this->factory->returns($this->kernel1, $this->kernel2);
    });

    describe('::install()', function () {
        it('adds the scope when a suite starts', function () {
            Scope::install($this->emitter->get(), $this->factory);

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
            Scope::install($this->emitter->get(), $this->factory);

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
});
