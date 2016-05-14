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

$client = new Predis\Async\Client('tcp://127.0.0.1:6379');

$client->connect(function ($client) {
    echo "Connected to Redis, now listening for incoming messages...\n";

    $client->pubSub('nrk:channel', function ($event, $pubsub) {
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

$client->getEventLoop()->run();
