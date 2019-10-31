# Changelog

## 0.3.5 (2019-10-31)

- **[IMPROVED]** Add `StreamWrapper::set_stream_option()` in preparation for PHP 7.4

## 0.3.4 (2017-12-13)

- **[IMPROVED]** Added functional tests for behaviour of `all()`, `any()`, `some()`
  and `first()` when no coroutines are provided

## 0.3.3 (2017-10-18)

- **[IMPROVED]** Update Recoil dependencies to stable releases

## 0.3.2 (2017-04-24)

- **[NEW]** Added `fdescribe()` `fcontext()` and `fit()` Peridot DSL functions
- **[IMPROVED]** Updated to `php-parser` version 3.x

## 0.3.1 (2017-01-13)

- **[FIX]** Fixed functional "strand tracing" kernel tests

## 0.3.0 (2017-01-11)

- **[BC]** Renamed `Scope::install()` to `PlugIn::install()`
- **[BC]** Remove `rit()` and `xrit()` functions, the regular Peridot DSL can now be used

## 0.2.0 (2017-01-09)

- **[BC]** Require `recoil/api` 1.0.0-alpha.2
- **[NEW]** Add functional tests for `select()` API operation

## 0.1.3 (2016-12-14)

- Allow `recoil/api` 1.0.0-alpha.1

## 0.1.2 (2016-12-13)

- **[NEW]** Added functional tests for callable as dispatchable values

## 0.1.1 (2016-12-12)

- **[FIX]** Instrumentation now works correctly with yield expressions
- **[NEW]** Added command line utility to instrument arbitrary files

## 0.1.0 (2016-12-07)

- Initial release
