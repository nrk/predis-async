<?php

require __DIR__.'/../autoload.php';

use React\EventLoop\StreamSelectLoop as EventLoop;

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

$client = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop = new EventLoop());

$client->getProfile()->defineCommand('lpushrand', 'ListPushRandomValue');

$client->connect(function ($client) {
    echo "Connected to Redis!\n";

    $client->script('load', ListPushRandomValue::LUA, function ($_, $_, $client) {
        $client->lpushrand('random_values', $seed = mt_rand(), function ($value, $_, $client) {
            var_dump($value);

            $client->disconnect();
        });
    });
});

$loop->run();
