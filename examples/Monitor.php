<?php

require __DIR__.'/../autoload.php';

use React\EventLoop\StreamSelectLoop as EventLoop;

$client = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop = new EventLoop());

$client->connect(function ($client) {
    echo "Connected to Redis, now listening for incoming messages...\n";

    $client->monitor(function ($event) {
        var_dump($event);
    });
});

$loop->run();
