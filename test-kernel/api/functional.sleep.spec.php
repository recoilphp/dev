<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

context('api/sleep', function () {
    rit('resumes execution after the specified number of seconds', function () {
        $operation = Recoil::sleep(0.02);
        $time = microtime(true);
        yield $operation;
        $diff = microtime(true) - $time;

        expect($diff)->to->be->within(0.01, 0.03);
    });

    rit('can be invoked by yielding a number', function () {
        $time = microtime(true);
        yield 0.02;
        $diff = microtime(true) - $time;

        expect($diff)->to->be->within(0.01, 0.03);
    });

    it('does not delay the kernel when a sleeping strand is terminated', function () {
        $time = microtime(true);
        $strand = $this->kernel()->execute(function () {
            yield Recoil::sleep(1);
        });
        $this->kernel()->execute(function () use ($strand) {
            $strand->terminate();
            yield;
        });
        $this->kernel()->run();
        $diff = microtime(true) - $time;

        expect($diff)->to->be->below(0.01);
    });

    rit('wakes strands in the correct order', function () {
        $strand1 = yield Recoil::execute(function () {
            yield 0.02;
            echo 'b';
        });

        $strand2 = yield Recoil::execute(function () {
            yield 0.01;
            echo 'a';
        });

        ob_start();
        yield $strand1;
        yield $strand2;
        expect(ob_get_clean())->to->equal('ab');
    });
});
