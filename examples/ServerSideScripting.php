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

class ListPushRandomValue extends Predis\Command\ScriptedCommand
{
    const LUA = <<<LUA
math.randomseed(ARGV[1])
local rnd = tostring(math.random())
redis.call('lpush', KEYS[1], rnd)
return rnd
LUA;

    public function getKeysCount()
    {
        return 1;
    }

    public function getScript()
    {
        return self::LUA;
    }
}

$client = new Predis\Async\Client('tcp://127.0.0.1:6379');

$client->getProfile()->defineCommand('lpushrand', 'ListPushRandomValue');

$client->connect(function ($client) {
    echo "Connected to Redis!\n";

    $client->script('load', ListPushRandomValue::LUA, function ($_, $client) {
        $client->lpushrand('random_values', $seed = mt_rand(), function ($value, $client) {
            var_dump($value);

            $client->disconnect();
        });
    });
});

$client->getEventLoop()->run();
