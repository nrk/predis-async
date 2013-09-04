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
