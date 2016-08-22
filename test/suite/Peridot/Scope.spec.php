<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Peridot;

use Eloquent\Phony\Phony;
use Evenement\EventEmitterInterface;
use Peridot\Core\Test;
use Recoil\Kernel;

describe(Scope::class, function () {
    beforeEach(function () {
        $this->emitter = Phony::mock(EventEmitterInterface::class);
        $this->test = new Test('description');
        $this->kernel = Phony::mock(Kernel::class);
        $this->factory = Phony::stub();
        $this->factory->returns($this->kernel);
    });

    describe('::install()', function () {
        it('adds the scope when a test starts', function () {
            Scope::install($this->emitter->get(), $this->factory);

            $fn = $this->emitter->on
                ->calledWith('suite.start', '~')
                ->firstCall()
                ->argument(1);

            $fn($this->test);

            $expected = new Scope($this->kernel->get());
            foreach ($this->test->getScope()->peridotGetChildScopes() as $scope) {
                if ($scope instanceof Scope) {
                    expect($scope->kernel())->to->equal($this->kernel->get());

                    return;
                }
            }

            expect(false)->to->be->ok('no kernel scope was added');
        });
    });

    describe('->kernel()', function () {
        it('returns the kernel', function () {
            $subject = new Scope($this->kernel->get());
            expect($subject->kernel())->to->equal($this->kernel->get());
        });
    });
});
