<?php

require __DIR__.'/../autoload.php';

use React\EventLoop\StreamSelectLoop as EventLoop;

$client = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop = new EventLoop());

$client->connect(function ($client) {
    echo "Connected to Redis!\n";

    $client->set('foo', 'bar', function ($response, $_, $client) {
        echo "`foo` has been set to `bar`, let's check if it's true... ";

        $client->get('foo', function($foo) {
            echo $foo === 'bar' ? 'YES! :-)' : 'NO :-(', "\n";
        });
    });
});

$loop->run();
