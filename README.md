# maksimovic/zend-cache

A PHP 8.1+ compatible fork of [zf1s/zend-cache](https://github.com/zf1s/zend-cache), originally from [Zend Framework 1](https://github.com/zendframework/zend-cache).

Caching implementation supporting File, SQLite3, Libmemcached, and BlackHole backends. The Sqlite backend has been rewritten to use the `SQLite3` extension. Backends for APC, XCache, Memcache (ext-memcache), WinCache, and ZendPlatform have been removed as their PHP extensions are not available on PHP 8+.

## Installation

```bash
composer require maksimovic/zend-cache
```

This package replaces `zendframework/zend-cache`, `zf1/zend-cache`, and `zf1s/zend-cache`.

## License

BSD-3-Clause
