<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async;

use \PHPUnit_Framework_TestCase as StandardTestCase;

use Predis\Connection\Parameters;
use Predis\Profile\Factory as ProfileFactory;
use React\EventLoop\StreamSelectLoop;

use Predis\Async\Configuration\Options;

/**
 *
 */
abstract class PredisAsyncTestCase extends StandardTestCase
{
    /**
     * Returns a new instance of connection parameters.
     *
     * @param array $override Override default connection parameters.
     *
     * @return Predis\Connection\ParametersInterface
     */
    protected function getParameters($override = null)
    {
        $parameters = new Parameters(array_merge(array(
            'scheme'  => 'tcp',
            'host'    => REDIS_SERVER_HOST,
            'port'    => REDIS_SERVER_PORT,
            'timeout' => 0.5,
        )), $override ?: array());

        return $parameters;
    }

    /**
     * Returns a new instance of client options.
     *
     * @param array $override Override default options.
     *
     * @return Predis\Async\Configuration\OptionsInterface
     */
    protected function getOptions($override = null)
    {
        $options = new Options(array_merge(array(
            'profile'   => ProfileFactory::get(REDIS_SERVER_VERSION),
            'eventloop' => $this->getEventLoop(),
        )), $override ?: array());

        return $options;
    }

    /**
     * Returns a new instance of event loop.
     *
     * @return StreamSelectLoop
     */
    protected function getEventLoop()
    {
        $loop = new StreamSelectLoop();

        return $loop;
    }

    /**
     * Returns a new instance of client.
     *
     * @param array $parameters Override default parameters.
     * @param array $override   Override default options.
     *
     * @return Client
     */
    public function getClient($parameters = null, $options = null)
    {
        $parameters = $this->getParameters();
        $options = $this->getOptions();

        $client = new Client($parameters, $options);

        return $client;
    }

    /**
     * Executes the callback with a client connected to Redis.
     *
     * @param mixed $callback   Callable object.
     * @param array $parameters Override default parameters.
     * @param array $override   Override default options.
     */
    public function withConnectedClient($callback, $parameters = null, $options = null)
    {
        $test = $this;

        $options = array_merge(array(
            'on_error' => function ($client, $exception) use ($test) {
                throw $exception;
            },
        ), $options ?: array());

        $client = $this->getClient($parameters, $options);
        $trigger = false;

        $client->connect(function ($client, $connection) use ($test, $callback, &$trigger) {
            $trigger = true;

            $client->select(REDIS_SERVER_DBNUM, function ($_, $client) use ($test, $callback, $connection) {
                call_user_func($callback, $test, $client, $connection);
            });
        });

        $loop = $client->getEventLoop();

        $loop->addTimer(0.01, function () use ($test, &$trigger) {
            $test->assertTrue($trigger, 'The client was unable to connect to Redis');
        });

        $loop->run();
    }

    /**
     * Detects the presence of the phpiredis extension.
     *
     * @return bool
     */
    public function isPhpiredisAvailable()
    {
        return function_exists('phpiredis_reader_create');
    }
}
