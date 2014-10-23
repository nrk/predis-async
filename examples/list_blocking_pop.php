<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require __DIR__.'/../autoload.php';

$loop = new React\EventLoop\StreamSelectLoop();

$consumer = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop);
$producer = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop);

$consumer->connect(function ($consumer) use ($producer) {
    echo "Connected to Redis, will BLPOP for max 10 seconds on `nrk:queue` and produce an item in ~5 seconds.\n";

    $start = microtime(true);

    $consumer->blpop('nrk:queue', 10, function ($response) use ($consumer, $producer, $start) {
        list($queue, $stop) = $response;

        $seconds = round((float) $stop - $start, 3);
        echo "Received item from `$queue` after $seconds seconds!\n";

        $consumer->disconnect();
        $producer->disconnect();
    });

    $consumer->getEventLoop()->addTimer(5, function () use ($producer) {
        $producer->lpush('nrk:queue', $microtime = microtime(true), function () use ($microtime) {
            echo "Just pushed $microtime to `nrk:queue`.\n";
        });
    });
});

$loop->run();
