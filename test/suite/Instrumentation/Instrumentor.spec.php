<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation;

use Eloquent\Phony\Phony;

describe(Instrumentor::class, function () {

    $this->fixtures = glob(__DIR__ . '/../../fixture/Instrumentation/*.input.php');

    context('when mode is ALL the code is instrumented', function () {
        beforeEach(function () {
            $this->subject = new Instrumentor(Mode::ALL());
        });

        foreach ($this->fixtures as $path) {
            it(explode('.', basename($path))[0], function () use ($path) {
                $source = file_get_contents($path);
                $result = $this->subject->instrument($source);

                // Use a phony spy for the expection so that we get nice diff output ...
                $spy = Phony::spy();
                $spy($result);
                $spy->calledWith(file_get_contents(str_replace('input', 'output', $path)));
            });
        }
    });

    context('when mode is NONE the code is not instrumented', function () {
        beforeEach(function () {
            $this->subject = new Instrumentor(Mode::NONE());
        });

        foreach ($this->fixtures as $path) {
            it(explode('.', basename($path))[0], function () use ($path) {
                $source = file_get_contents($path);
                $result = $this->subject->instrument($source);

                // Use a phony spy for the expection so that we get nice diff output ...
                $spy = Phony::spy();
                $spy($result);
                $spy->calledWith($source);
            });
        }
    });

});
