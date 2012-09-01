<?php

require __DIR__.'/../autoload.php';

use React\EventLoop\StreamSelectLoop as EventLoop;

$client = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop = new EventLoop());

$client->connect(function ($client) {
    echo "Connected to Redis, now listening for incoming messages...\n";

    $logger = new Predis\Async\Client('tcp://127.0.0.1:6379', $client->getEventLoop());

    $client->subscribe('nrk:channel', function ($event) use ($logger) {
        list(, $channel, $msg) = $event;

        $logger->rpush("store:$channel", $msg, function () use ($channel, $msg) {
            echo "Stored message `$msg` from $channel.\n";
        });
    });
});

$loop->run();
