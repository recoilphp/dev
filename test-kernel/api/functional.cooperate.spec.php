<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Recoil\Kernel\Strand;

context('api/cooperate', function () {
    it('yields control to another strand', function () {
        ob_start();

        yield Recoil::execute(function () {
            echo 'b';

            return;
            yield;
        });

        echo 'a';
        yield Recoil::cooperate();
        echo 'c';

        expect(ob_get_clean())->to->equal('abc');
    });

    it('can be invoked by yielding null', function () {
        ob_start();

        yield Recoil::execute(function () {
            echo 'b';

            return;
            yield;
        });

        echo 'a';
        yield;
        echo 'c';

        expect(ob_get_clean())->to->equal('abc');
    });
});
