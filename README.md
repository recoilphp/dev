# Recoil Development Tools

[![Build Status](http://img.shields.io/travis/recoilphp/dev/master.svg?style=flat-square)](https://travis-ci.org/recoilphp/dev)
[![Code Coverage](https://img.shields.io/codecov/c/github/recoilphp/dev/master.svg?style=flat-square)](https://codecov.io/github/recoilphp/dev)
[![Code Quality](https://img.shields.io/scrutinizer/g/recoilphp/dev/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/recoilphp/dev/)
[![Latest Version](http://img.shields.io/packagist/v/recoil/dev.svg?style=flat-square&label=semver)](https://semver.org)

Development and debugging tools for [Recoil](https://github.com/recoilphp/recoil) applications.

    composer require --dev recoil/dev

## Usage

Primarily, `recoil/dev` is a Composer plugin that automatically instruments
coroutine functions to provide meaningful stack traces in the event of an
exception. Without `recoil/dev`, stack traces tend to show details about the
internals of the Recoil kernel, rather than the coroutines it is executing.

Instrumentation is performed automatically, by plugging in to the autoload 
pipeline. For most use cases no change is required in your code (other than the
note below), and you should immediately see more usable stack traces.

The instrumentor identifies functions as coroutines if they have a return type
hint of `Coroutine`, where `Coroutine` is an alias for `Generator`, for example:

```php
// Alias Generator as Coroutine.
use Generator as Coroutine;

function doNothing(int $value): Coroutine // Mark function as a coroutine.
{
    yield;
}
```

The instrumentor will not instrument functions that use a return type hint of
`Generator`, as without the alias it has no way to distinguish between an actual
coroutine and a regular generator function.

## Troubleshooting

Check the following:
1. Ensure that [assertions are 
enabled](https://www.php.net/manual/en/function.assert.php) on your development 
environment.
2. Ensure that your coroutines are explicitly identified using `: Coroutine` 
type hint, as described above.
3. Syntax errors in your source files may break the PHP parsing used to 
generate instrumentation, leaving those files uninstrumentated -- check your
syntax!

## Building and testing

Please see [CONTRIBUTING.md](.github/CONTRIBUTING.md) for information about
running the tests and submitting changes.
