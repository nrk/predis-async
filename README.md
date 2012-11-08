# Predis\Async #

An asynchronous (non-blocking) version of [Predis](https://github.com/nrk/predis), the full-featured
PHP client library for [Redis](http://redis.io), built on top of [React](https://github.com/reactphp)
to handle evented I/O and [phpiredis](https://github.com/seppo0010/phpiredis) to serialize and parse
the Redis protocol with the speed benefits of a C extension.

Predis\Async is currently under development but already works pretty well. The client foundation is
being built on top of the event loop abstraction offered by [React](https://github.com/reactphp), a
new event-oriented framework for PHP under heavy-development that aims to provide everything needed
to create reusable components and applications using an evented approach with non-blocking I/O.

Contributions are highly welcome and appreciated, feel free to open pull-requests with fixes or just
[report issues](https://github.com/nrk/predis-async/issues) if you encounter weird behaviors or blatant
bugs.

## Main features ##

- Wide range of Redis versions supported (from __1.2__ to __2.6__ and unstable) using server profiles.
- Transparent key prefixing strategy capable of handling any command known that has keys in its arguments.
- Abstraction for `MULTI` / `EXEC` transactions (Redis >= 2.0).
- Abstraction for Pub/Sub with `PUBLISH`, `SUBSCRIBE` and the other related commands (Redis >= 2.0).
- Abstraction for `MONITOR` contexts (Redis >= 1.2).
- Abstraction for Lua scripting (Redis >= 2.6).
- Ability to connect to Redis using TCP/IP or UNIX domain sockets.
- The connection to Redis can be lazily established, commands are queued while the client is connecting.
- Flexible system to define and register your own set of commands or server profiles to client instances.

## Installing ##

Predis\Async is available on [Packagist](http://packagist.org/packages/predis/predis-async) and can
be installed through [Composer](http://getcomposer.org/). Using it in your application is simply a
matter of adding `"predis/predis-async": "dev-master"` in your `composer.json` list of `require`ed
libraries. Also remember that you must have [phpiredis](https://github.com/seppo0010/phpiredis)
pre-installed as a PHP extension.

## Example ##

``` php
<?php
require __DIR__.'/../autoload.php';

$client = new Predis\Async\Client('tcp://127.0.0.1:6379');

$client->connect(function ($client) {
    echo "Connected to Redis, now listening for incoming messages...\n";

    $logger = new Predis\Async\Client('tcp://127.0.0.1:6379', $client->getEventLoop());

    $client->pubsub('nrk:channel', function ($event) use ($logger) {
        $logger->rpush("store:{$event->channel}", $event->payload, function () use ($event) {
            echo "Stored message `{$event->payload}` from {$event->channel}.\n";
        });
    });
});

$client->getEventLoop()->run();
```

## Differences with Predis ##

Being an asynchronous client implementation, the underlying design of Predis\Async is quite different
from the one of Predis which is a blocking implementation. Certain features have not been implemented
yet (or cannot be implemented at all), just to name a few you will not find the usual abstractions for
command pipelines and client-side sharding. That said, the two libraries use a common style and share
a few common classes so if you already are a user of Predis you should feel at home.

## Current TODO list ##

- Complement the test suite with more test cases.
- Try to implement aggregated connections and add an abstraction for master/slave replication.

## Contributing ##

If you want to work on Predis\Async, it is highly recommended that you first run the test suite in
order to check that everything is OK, and report strange behaviours or bugs. When modifying the code
please make sure that no warnings or notices are emitted by PHP by running the interpreter in your
development environment with the `error_reporting` variable set to `E_ALL | E_STRICT`.

The recommended way to contribute to Predis\Async is to fork the project on GitHub, create new topic
branches on your newly created repository to fix or add features (possibly with tests covering your
modifications) and then open a new pull request with a description of the applied changes. Obviously
you can use any other Git hosting provider of your preference.

Please also follow some basic [commit guidelines](http://git-scm.com/book/ch5-2.html#Commit-Guidelines)
before opening pull requests.

## Dependencies ##

- [PHP](http://www.php.net/) >= 5.3.2
- [Predis](https://github.com/nrk/predis) (Git master branch)
- [phpiredis](https://github.com/seppo0010/phpiredis) (Git master branch)
- [React/EventLoop](https://github.com/reactphp/event-loop) >= v0.1.0
- [PHPUnit](http://www.phpunit.de/) >= 3.5.0 (needed to run the test suite)

### Project ###
- [Source code](http://github.com/nrk/predis-async/)
- [Issue tracker](http://github.com/nrk/predis-async/issues)

## Author ##

- [Daniele Alessandri](mailto:suppakilla@gmail.com) ([twitter](http://twitter.com/JoL1hAHN))

## License ##

The code for Predis\Async is distributed under the terms of the MIT license (see LICENSE).
