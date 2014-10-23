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
    echo "Connected to Redis!\n";

    $tx = $client->transaction();
    $tx->ping();
    $tx->echo("FOO");
    $tx->echo("BAR");
    $tx->execute(function ($replies, $client) {
        var_dump($replies);

        $client->info('cpu', function ($cpuInfo, $client) {
            var_dump($cpuInfo);

            $client->disconnect();
        });
    });
});

$client->getEventLoop()->run();
