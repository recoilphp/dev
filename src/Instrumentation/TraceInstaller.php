<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation;

use Recoil\Kernel\Api;
use Recoil\Kernel\Awaitable;
use Recoil\Kernel\Listener;
use Recoil\Kernel\Strand;

final class TraceInstaller implements Awaitable
{
    public function await(Listener $listener, Api $api)
    {
        assert($listener instanceof Strand);

        $trace = $listener->trace();

        if ($trace === null) {
            $trace = new Trace();
            $listener->setTrace($trace);
        }

        if ($trace instanceof Trace) {
            $listener->send($trace);
        } else {
            $listener->send(null);
        }
    }
}
