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

use Predis\ClientException;
use Predis\NotSupportedException;
use Predis\ResponseObjectInterface;
use Predis\Command\CommandInterface;
use Predis\Connection\ConnectionParameters;
use Predis\Connection\ConnectionParametersInterface;
use Predis\Option\ClientOptionsInterface;
use Predis\Profile\ServerProfile;
use Predis\Profile\ServerProfileInterface;
use Predis\Async\Connection\ConnectionInterface;
use Predis\Async\Connection\StreamConnection;
use Predis\Async\Monitor\MonitorContext;
use Predis\Async\Option\ClientOptions;
use Predis\Async\PubSub\PubSubContext;
use Predis\Async\Transaction\MultiExecContext;
use React\EventLoop\LoopInterface;

/**
 * Main class that exposes the most high-level interface to interact asynchronously
 * with Redis instances.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class Client
{
    const VERSION = '0.2.3';

    protected $profile;
    protected $connection;

    /**
     * Initializes a new client with optional connection parameters and client options.
     *
     * @param mixed $parameters Connection parameters for one or multiple servers.
     * @param mixed $options    Options that specify certain behaviours for the client.
     */
    public function __construct($parameters = null, $options = null)
    {
        $this->options = $options = $this->filterOptions($options ?: array());
        $this->connection = $this->initializeConnection($parameters, $options);
        $this->profile = $options->profile;
    }

    /**
     * Creates connection parameters.
     *
     * @param mixed $parameters Connection parameters.
     *
     * @return ConnectionParametersInterface
     */
    protected function filterParameters($parameters)
    {
        if ($parameters instanceof ConnectionParametersInterface) {
            return $parameters;
        }

        return new ConnectionParameters($parameters ?: array());
    }

    /**
     * Creates an instance of Predis\Option\ClientOptions from various types of
     * arguments (string, array, Predis\Profile\ServerProfile) or returns the
     * passed object if it is an instance of Predis\Option\ClientOptions.
     *
     * @param mixed $options Client options.
     *
     * @return ClientOptions
     */
    protected function filterOptions($options)
    {
        if (is_array($options)) {
            return new ClientOptions($options);
        }

        if ($options instanceof LoopInterface) {
            return new ClientOptions(array('eventloop' => $options));
        }

        if ($options instanceof ClientOptionsInterface) {
            return $options;
        }

        throw new \InvalidArgumentException('Invalid type for client options');
    }

    /**
     * Initializes one or multiple connection (cluster) objects from various
     * types of arguments (string, array) or returns the passed object if it
     * implements Predis\Connection\ConnectionInterface.
     *
     * @param mixed                  $parameters Connection parameters or instance.
     * @param ClientOptionsInterface $options    Client options.
     *
     * @return ConnectionInterface
     */
    protected function initializeConnection($parameters, ClientOptionsInterface $options)
    {
        if ($parameters instanceof ConnectionInterface) {
            if ($parameters->getEventLoop() !== $this->options->eventloop) {
                throw new ClientException('Client and connection must share the same event loop instance');
            }

            return $parameters;
        }

        $parameters = $this->filterParameters($parameters);
        $connection = new StreamConnection($parameters, $this->options->eventloop);

        if (isset($options->on_error)) {
            $this->setErrorCallback($connection, $options->on_error);
        }

        return $connection;
    }

    /**
     * Sets the callback used to notify the client after a connection error.
     *
     * @param ConnectionInterface $connection Connection instance.
     * @param mixed               $callback   Callback for error event.
     */
    protected function setErrorCallback(ConnectionInterface $connection, $callback)
    {
        $client = $this;

        $connection->setErrorCallback(function ($connection, $exception) use ($callback, $client) {
            call_user_func($callback, $client, $exception, $connection);
        });
    }

    /**
     * Returns the server profile used by the client.
     *
     * @return ServerProfileInterface
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * Returns the underlying event loop.
     *
     * @return LoopInterface
     */
    public function getEventLoop()
    {
        return $this->options->eventloop;
    }

    /**
     * Returns the client options specified upon initialization.
     *
     * @return ClientOptionsInterface
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Opens the connection to the server.
     *
     * @param mixed $callback Callback for connection event.
     */
    public function connect($callback)
    {
        $client = $this;

        $callback = function ($connection) use ($callback, $client) {
            call_user_func($callback, $client, $connection);
        };

        $this->connection->connect($callback);
    }

    /**
     * Disconnects from the server.
     */
    public function disconnect()
    {
        $this->connection->disconnect();
    }

    /**
     * Checks if the underlying connection is connected to Redis.
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->connection->isConnected();
    }

    /**
     * Returns the underlying connection instance.
     *
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function __call($method, $arguments)
    {
        if (false === is_callable($callback = array_pop($arguments))) {
            $arguments[] = $callback;
            $callback = null;
        }

        $command = $this->profile->createCommand($method, $arguments);
        $this->executeCommand($command, $callback);
    }

    /**
     * Creates a new instance of the specified Redis command.
     *
     * @param string $method    The name of a Redis command.
     * @param array  $arguments The arguments for the command.
     *
     * @return CommandInterface
     */
    public function createCommand($method, $arguments = array())
    {
        return $this->profile->createCommand($method, $arguments);
    }

    /**
     * Executes the specified Redis command.
     *
     * @param CommandInterface $command  A Redis command.
     * @param mixed            $callback Optional command callback.
     */
    public function executeCommand(CommandInterface $command, $callback = null)
    {
        $this->connection->executeCommand($command, $this->wrapCallback($callback));
    }

    /**
     * Wraps a command callback to parse the raw response returned by a
     * command and pass more arguments back to user code.
     *
     * @param mixed $callback Command callback.
     */
    protected function wrapCallback($callback)
    {
        $client = $this;

        return function ($response, $connection, $command) use ($client, $callback) {
            if (false === isset($callback)) {
                return;
            }

            if (true === isset($command) && false === $response instanceof ResponseObjectInterface) {
                $response = $command->parseResponse($response);
            }

            call_user_func($callback, $response, $client, $command);
        };
    }

    /**
     * Creates a new transaction context.
     *
     * @return MultiExecContext
     */
    public function multiExec(/* arguments */)
    {
        return new MultiExecContext($this);
    }

    /**
     * Creates a new monitor context.
     *
     * @param mixed $callback Callback invoked on each payload message.
     *
     * @return MonitorContext
     */
    public function monitor($callback, $autostart = true)
    {
        $monitor = new MonitorContext($this, $callback);

        if (true == $autostart) {
            $monitor->start();
        }

        return $monitor;
    }

    /**
     * Creates a new pub/sub context.
     *
     * @param mixed $channels List of channels for subscription.
     * @param mixed $callback Callback invoked on each payload message.
     *
     * @return PubSubContext
     */
    public function pubSub($channels, $callback)
    {
        $pubsub = new PubSubContext($this, $callback);

        if (true === is_string($channels)) {
            $channels = array('subscribe' => array($channels));
        }

        if (isset($channels['subscribe'])) {
            $pubsub->subscribe($channels['subscribe']);
        }

        if (isset($channels['psubscribe'])) {
            $pubsub->psubscribe($channels['psubscribe']);
        }

        return $pubsub;
    }

    /**
     * {@inheritdoc}
     */
    public function pipeline(/* arguments */)
    {
        throw new NotSupportedException('Not yet implemented');
    }
}
