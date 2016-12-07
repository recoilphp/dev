<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

context('api/stop', function () {
    it('stops the kernel', function () {
        $kernel = $this->kernel();

        $kernel->execute(function () {
            yield Recoil::stop();
            expect(false)->to->be->ok('strand was resumed');
        });

        $kernel->run();
    });

    it('does not resume any strands', function () {
        $kernel = $this->kernel();

        $kernel->execute(function () {
            yield;
            expect(false)->to->be->ok('strand was resumed');
        });

        $kernel->execute(function () {
            yield Recoil::stop();
        });

        $kernel->run();
    });

    it('does not wait for sleeping strands', function () {
        $kernel = $this->kernel();

        $kernel->execute(function () {
            yield 5;
        });

        $kernel->execute(function () {
            yield Recoil::stop();
        });

        $time = microtime(true);
        $kernel->run();
        $diff = microtime(true) - $time;

        expect($diff)->to->be->below(0.05);
    });
});
