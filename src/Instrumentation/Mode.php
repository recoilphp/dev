<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation;

use Eloquent\Enumeration\AbstractEnumeration;

final class Mode extends AbstractEnumeration
{
    const ALL = 'all';
    const NONE = 'none';
}
