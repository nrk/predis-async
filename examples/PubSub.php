<?php

require __DIR__.'/../autoload.php';

use React\EventLoop\StreamSelectLoop as EventLoop;

$client = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop = new EventLoop());

$client->connect(function ($client) {
    echo "Connected to Redis, now listening for incoming messages...\n";

    $logger = new Predis\Async\Client('tcp://127.0.0.1:6379', $client->getEventLoop());

    $client->pubsub('nrk:channel', function ($event, $pubsub) use ($logger) {
        $message = "Received message `%s` from channel `%s` [type: %s].\n";

        $feedback = sprintf($message,
            $event->payload,
            $event->channel,
            $event->kind
        );

        echo $feedback;

        if ($event->payload === 'quit') {
            $pubsub->quit();
        }
    });
});

$loop->run();
