<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation\Autoloader;

use Eloquent\Phony\Phony;
use Recoil\Dev\Instrumentation\Instrumentor;
use Recoil\Dev\Instrumentation\Mode;

describe(StreamWrapper::class, function () {

    $this->fixtures = glob(__DIR__ . '/../../../fixture/Instrumentation/Instrumentor/*.input.php');

    context('when mode is ALL the code is instrumented', function () {
        beforeEach(function () {
            $this->scheme = StreamWrapper::install(Instrumentor::create(Mode::ALL()));
        });

        foreach ($this->fixtures as $path) {
            it(explode('.', basename($path))[0], function () use ($path) {
                $source = file_get_contents($this->scheme . '://' . $path);

                // Use a phony spy for the expection so that we get nice diff output ...
                $spy = Phony::spy();
                $spy($source);
                $spy->calledWith(file_get_contents(str_replace('input', 'output', $path)));
            });
        }
    });

    context('when mode is NONE the code is not instrumented', function () {
        beforeEach(function () {
            $this->scheme = StreamWrapper::install(Instrumentor::create(Mode::NONE()));
        });

        foreach ($this->fixtures as $path) {
            it(explode('.', basename($path))[0], function () use ($path) {
                $source = file_get_contents($this->scheme . '://' . $path);

                // Use a phony spy for the expection so that we get nice diff output ...
                $spy = Phony::spy();
                $spy($source);
                $spy->calledWith($source);
            });
        }
    });

});
