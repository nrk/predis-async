v0.3.0 (2014-xx-xx)
===============================================================================

- Switched to PSR-4 for autoloading.

- Switched to react/event-loop 0.4 and predis/predis 1.0. This change breaks the
  compatibility with previous versions of Predis\Async due to the changes needed
  to adapt to the new (and stable) API of Predis. Support for PHP 5.3 has been
  dropped since newer versions of React require PHP >= 5.4.

- The phpiredis extension is now optional and by default the client uses a pure
  PHP protocol serializer / parser provided by the clue/redis-protocol library.

v0.2.2 (2013-09-04)
===============================================================================

 - __FIX__: when connection refused exceptions are thrown the client gets stuck
   in a retry loop that keeps on raising exceptions until the specified server
   starts accepting new connections again.

v0.2.1 (2013-08-27)
===============================================================================

- __FIX__: properly release subscription to write events when the write buffer
  is empty (ISSUE #5).

v0.2.0 (2013-07-27)
===============================================================================

- The library now requires react/event-loop v0.3.x.

v0.1.0 (2013-07-27)
===============================================================================

- First versioned release of Predis\Async. This should be used if you still
  depend on react v0.2.x.
