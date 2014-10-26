v0.2.3 (2014-10-26)
===============================================================================

- __FIX__: when the client has commands being queued while the connect operation
  is pending, once the connection is established the underlying socket should
  not be set as readable if the buffer is not empty.

- __FIX__: avoid E_WARN messages from being emitted when socket creation fails
  early e.g. when pointing to client to a non-existent UNIX domain socket file).

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
