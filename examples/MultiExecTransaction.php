<?php

require __DIR__.'/../autoload.php';

use React\EventLoop\StreamSelectLoop as EventLoop;

$client = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop = new EventLoop());

$client->connect(function ($client) {
    echo "Connected to Redis!\n";

    $tx = $client->multiExec();
    $tx->ping();
    $tx->echo("FOO");
    $tx->echo("BAR");
    $tx->execute(function ($replies, $client) {
        var_dump($replies);

        $client->info('cpu', function ($cpuInfo) {
            var_dump($cpuInfo);
        });
    });
});

$loop->run();
