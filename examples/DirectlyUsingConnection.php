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

$server = 'tcp://127.0.0.1:6379';
$loop = new EventLoop();

$parameters = new Predis\Connection\ConnectionParameters($server);
$connection = new Predis\Async\Connection\StreamConnection($parameters, $loop);
$profile    = Predis\Profile\ServerProfile::getDefault();

$connection->connect(function ($connection) use ($profile) {
    $ping = $profile->createCommand('ping');

    $connection->executeCommand($ping, function ($response, $connection) use ($profile) {
        $echo = $profile->createCommand('echo', array($response));

        $connection->executeCommand($echo, function ($response, $connection) {
            var_dump($response);

            $connection->disconnect();
        });
    });
});

$loop->run();
