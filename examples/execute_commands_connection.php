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
$parameters = Predis\Connection\Parameters::create('tcp://127.0.0.1:6379');
$profile = Predis\Profile\Factory::getDefault();

$connection = new Predis\Async\Connection\PhpiredisStreamConnection($loop, $parameters);

$connection->connect(function ($connection) use ($profile) {
    $ping = $profile->createCommand('ping');

    $connection->executeCommand($ping, function ($response, $connection) use ($profile) {
        $echo = $profile->createCommand('echo', [$response]);

        $connection->executeCommand($echo, function ($response, $connection) {
            var_dump($response);

            $connection->disconnect();
        });
    });
});

$loop->run();
