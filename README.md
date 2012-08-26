# Predis\Async #

An asynchronous (non-blocking) version of [Predis](https://github.com/nrk/predis), the flexible and
feature-complete PHP client library for the [Redis](http://redis.io) key-value store, built on top
of [React](https://github.com/react-php) using [phpiredis](https://github.com/seppo0010/phpiredis)
to benefit from parsing the Redis protocol from inside a PHP extension.

Predis\Async is currently __highly experimental__ which means that it is unstable, lacks various
features and the API is ugly and not yet finalized as of now. The client foundation is being built
on top of the evented loop abstraction offered by [React](https://github.com/react-php), a new event
oriented framework for PHP under heavy-development aiming to provide everything needed to create
reusable components and applications using an evented approach with non-blocking I/O.

I would like to stress the fact that the code in Predis\Async is addmittedly still ugly and blatant
bugs are most likely right around the corner, but feel free to open pull-requests with fixes or just
[report them](https://github.com/nrk/predis-async/issues).

Contributions to both Predis\Async and React are highly welcome and appreciated.

## Installing ##

Predis\Async is available on [Packagist](http://packagist.org/packages/predis/predis-async) and can
be installed through [Composer](http://getcomposer.org/). Using it to your application is simply a
matter of adding `"predis/predis-async": "dev-master"` in your `composer.json` list of `require`ed
libraries. Also remember that you must have [phpiredis](https://github.com/seppo0010/phpiredis)
pre-installed as a PHP extension.

## Example ##

``` php
<?php
require __DIR__.'/../autoload.php';

use Predis\Async\Client as PredisAsync;
use React\EventLoop\StreamSelectLoop as EventLoop;

$client = new PredisAsync('tcp://127.0.0.1:6379', $loop = new EventLoop());

$client->connect(function ($client) {
    echo "Connected to Redis, now listening for incoming messages...\n";

    $clientLogger = new PredisAsync('tcp://127.0.0.1:6379', $client->getEventLoop());

    $client->subscribe('nrk:channel', function ($event) use ($clientLogger) {
        list(, $channel, $msg) = $event;

        $clientLogger->rpush("store:$channel", $msg, function () use ($channel, $msg) {
            echo "Stored message `$msg` from $channel.\n";
        });
    });
});

$loop->run();
```

## Differences with Predis ##

Being an asynchronous client implementation, the underlying design of Predis\Async is quite different
from the one of Predis which is a blocking implementation. Certain features have not been implemented
yet (or cannot be implemented at all), just to name a few you will not find the usual abstractions for
command pipelines and client-side sharding. That said, the two libraries share a few common classes
making it possible, for example, to use different server profiles or define commands with their own
arguments filter / reply parser.

## Most immediate TODO list ##

- Provide a better API that suits best the asynchronous model.
- Add tests. Lots of them.
- Everything else.

## Contributing ##

If you want to work on Predis\Async, it is highly recommended that you first run the test suite in
order to check that everything is OK, and report strange behaviours or bugs. When modifying Predis
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
- [React](https://github.com/react-php) >= v0.1.0
- [PHPUnit](http://www.phpunit.de/) >= 3.5.0 (needed to run the test suite)

### Project ###
- [Source code](http://github.com/nrk/predis-async/)
- [Issue tracker](http://github.com/nrk/predis-async/issues)

## Author ##

- [Daniele Alessandri](mailto:suppakilla@gmail.com) ([twitter](http://twitter.com/JoL1hAHN))

## License ##

The code for Predis\Async is distributed under the terms of the MIT license (see LICENSE).
