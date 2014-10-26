# Predis\Async #

[![Latest Stable Version](https://poser.pugx.org/predis/predis-async/v/stable.png)](https://packagist.org/packages/predis/predis-async)
[![Total Downloads](https://poser.pugx.org/predis/predis-async/downloads.png)](https://packagist.org/packages/predis/predis-async)
[![License](https://poser.pugx.org/predis/predis-async/license.svg)](https://packagist.org/packages/predis/predis-async)
[![Build Status](https://travis-ci.org/nrk/predis-async.svg?branch=master)](https://travis-ci.org/nrk/predis-async)
[![HHVM Status](http://hhvm.h4cc.de/badge/predis-async/predis-async.png)](http://hhvm.h4cc.de/package/predis/predis-async)

Asynchronous (non-blocking) version of [Predis](https://github.com/nrk/predis), the full-featured
PHP client library for [Redis](http://redis.io), built on top of [React](http://reactphp.org/) to
handle evented I/O. By default Predis\Async does not require any additional C extension to work, but
it can be optionally paired with [phpiredis](https://github.com/nrk/phpiredis) to sensibly lower the
overhead of serializing and parsing the Redis protocol.

Predis\Async is currently under development but already works pretty well. The client foundation is
being built on top of the event loop abstraction offered by [React](https://github.com/reactphp), an
event-oriented framework for PHP that aims to provide everything needed to create reusable libraries
and long-running applications using an evented approach powered by non-blocking I/O. This library is
partially tested on [HHVM](http://www.hhvm.com), but support for this runtime should be considered
experimental.

Contributions are highly welcome and appreciated, feel free to open pull-requests with fixes or just
[report issues](https://github.com/nrk/predis-async/issues) if you encounter weird behaviors and
blatant bugs.

## Main features ##

- Wide range of Redis versions supported (from __2.0__ to __3.0__ and __unstable__) using profiles.
- Transparent key prefixing for all known Redis commands using a customizable prefixing strategy.
- Abstraction for `MULTI` / `EXEC` transactions (Redis >= 2.0).
- Abstraction for `PUBLISH` / `SUBSCRIBE` contexts (Redis >= 2.0).
- Abstraction for `MONITOR` contexts (Redis >= 1.2).
- Abstraction for Lua scripting (Redis >= 2.6).
- Ability to connect to Redis using TCP/IP or UNIX domain sockets.
- Redis connections can be established lazily, commands are queued while the client is connecting.
- Flexible system for defining and registering custom sets of supported commands or profiles.

## Installing ##

Predis\Async is available on [Packagist](http://packagist.org/packages/predis/predis-async). It is
not required to have the [phpiredis](https://github.com/nrk/phpiredis) extension loaded as suggested
since the client will work anyway using a pure-PHP protocol parser, but if the extension is detected
at runtime then it will be automatically preferred over the slower default. It is possible to force
the client to use the pure-PHP protocol parser even when the extension is detected simply by passing
`['phpiredis' => false]` in the array of client options.

## Example ##

``` php
<?php
require __DIR__.'/../autoload.php';

$loop = new React\EventLoop\StreamSelectLoop();
$client = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop);

$client->connect(function ($client) use ($loop) {
    echo "Connected to Redis, now listening for incoming messages...\n";

    $logger = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop);

    $client->pubSubLoop('nrk:channel', function ($event) use ($logger) {
        $logger->rpush("store:{$event->channel}", $event->payload, function () use ($event) {
            echo "Stored message `{$event->payload}` from {$event->channel}.\n";
        });
    });
});

$loop->run();
```

## Differences with Predis ##

Being an asynchronous client implementation, the underlying design of Predis\Async is different from
the one of Predis which is a blocking implementation. Certain features have not been implemented yet
(or cannot be implemented at all), just to name a few you will not find the usual abstractions for
pipelining commands and creating cluster of nodes using client-side sharding. That said, they share
a common style and a few basic classes so if you used Predis in the past you should feel at home.

## Contributing ##

If you want to work on Predis\Async, it is highly recommended that you first run the test suite in
order to check that everything is OK, and report strange behaviours or bugs. When modifying the code
please make sure that no warnings or notices are emitted by PHP by running the interpreter in your
development environment with the `error_reporting` variable set to `E_ALL | E_STRICT`.

The recommended way to contribute to Predis\Async is to fork the project on GitHub, create new topic
branches on your newly created repository to fix or add features (possibly with tests covering your
modifications) and then open a new pull request with a description of the applied changes. Obviously
you can use any other Git hosting provider of your preference.

Please follow a few basic [commit guidelines](http://git-scm.com/book/ch5-2.html#Commit-Guidelines)
before opening pull requests.

### Project ###
- [Source code](https://github.com/nrk/predis-async/)
- [Issue tracker](https://github.com/nrk/predis-async/issues)

## Author ##

- [Daniele Alessandri](mailto:suppakilla@gmail.com) ([twitter](http://twitter.com/JoL1hAHN))

## License ##

The code for Predis\Async is distributed under the terms of the MIT license (see LICENSE).
