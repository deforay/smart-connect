# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 2.7.0 - 2018-05-14

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- [zendframework/zend-xmlrpc#32](https://github.com/zendframework/zend-xmlrpc/pull/32) removes support for HHVM.

### Fixed

- Nothing.

## 2.6.2 - 2018-01-25

### Added

- [zendframework/zend-xmlrpc#29](https://github.com/zendframework/zend-xmlrpc/pull/29) adds support for
  PHP 7.2, by replacing deprecated `list`/`each` syntax with a functional
  equivalent.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 2.6.1 - 2017-08-11

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-xmlrpc#27](https://github.com/zendframework/zend-xmlrpc/pull/27) fixed a memory leak
  caused by repetitive addition of `Accept` and `Content-Type` headers on subsequent
  HTTP requests produced by the `Laminas\XmlRpc\Client`.

## 2.6.0 - 2016-06-21

### Added

- [zendframework/zend-xmlrpc#19](https://github.com/zendframework/zend-xmlrpc/pull/19) adds support for
  laminas-math v3.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 2.5.2 - 2016-04-21

### Added

- [zendframework/zend-xmlrpc#11](https://github.com/zendframework/zend-xmlrpc/pull/11),
  [zendframework/zend-xmlrpc#12](https://github.com/zendframework/zend-xmlrpc/pull/12),
  [zendframework/zend-xmlrpc#13](https://github.com/zendframework/zend-xmlrpc/pull/13),
  [zendframework/zend-xmlrpc#14](https://github.com/zendframework/zend-xmlrpc/pull/14),
  [zendframework/zend-xmlrpc#15](https://github.com/zendframework/zend-xmlrpc/pull/15), and
  [zendframework/zend-xmlrpc#16](https://github.com/zendframework/zend-xmlrpc/pull/16)
  added and prepared the documentation for publication at
  https://docs.laminas.dev/laminas-xmlrpc/

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-xmlrpc#17](https://github.com/zendframework/zend-xmlrpc/pull/17) updates
  dependencies to allow laminas-stdlib v3 releases.
