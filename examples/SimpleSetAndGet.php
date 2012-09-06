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

use React\EventLoop\StreamSelectLoop as EventLoop;

$client = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop = new EventLoop());

$client->connect(function ($client) {
    echo "Connected to Redis!\n";

    $client->set('foo', 'bar', function ($response, $client) {
        echo "`foo` has been set to `bar`, let's check if it's true... ";

        $client->get('foo', function($foo, $client) {
            echo $foo === 'bar' ? 'YES! :-)' : 'NO :-(', "\n";

            $client->disconnect();
        });
    });
});

$loop->run();
