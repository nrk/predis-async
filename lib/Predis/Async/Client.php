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

use Predis\ConnectionParametersInterface;
use Predis\Command\CommandInterface;
use Predis\Option\ClientOptionsInterface;
use Predis\Profile\ServerProfileInterface;
use Predis\ClientException;
use Predis\ConnectionParameters;
use Predis\NotSupportedException;
use Predis\Profile\ServerProfile;

use Predis\Async\Connection\AsynchronousConnectionInterface;
use Predis\Async\Option\ClientOptions;
use Predis\Async\Connection\AsynchronousConnection;

use React\EventLoop\LoopInterface;

/**
 * Main class that exposes the most high-level interface to interact asynchronously
 * with Redis instances.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class Client
{
    const VERSION = '0.0.1-dev';
    const DEFAULT_SERVER = 'tcp://127.0.0.1:6379';

    protected $profile;
    protected $connection;
    protected $eventLoop;

    /**
     * Initializes a new client with optional connection parameters and client options.
     *
     * @param mixed $parameters Connection parameters for one or multiple servers.
     * @param mixed $options Options that specify certain behaviours for the client.
     */
    public function __construct($parameters = null, $options = null)
    {
        $parameters = $this->filterParameters($parameters);
        $options = $this->filterOptions($options);

        $this->options = $options;
        $this->profile = $options->profile;
        $this->eventLoop = $options->eventloop;

        $this->connection = $this->initializeConnection($parameters, $options);
    }

    /**
     * Creates connection parameters.
     *
     * @param mixed $options Connection parameters.
     * @return ConnectionParametersInterface
     */
    protected function filterParameters($parameters)
    {
        if (!$parameters instanceof ConnectionParametersInterface) {
            $parameters = new ConnectionParameters($parameters);
        }

        return $parameters;
    }

    /**
     * Creates an instance of Predis\Option\ClientOptions from various types of
     * arguments (string, array, Predis\Profile\ServerProfile) or returns the
     * passed object if it is an instance of Predis\Option\ClientOptions.
     *
     * @param mixed $options Client options.
     * @return ClientOptions
     */
    protected function filterOptions($options)
    {
        if ($options === null) {
            return new ClientOptions();
        }
        if (is_array($options)) {
            return new ClientOptions($options);
        }
        if ($options instanceof ClientOptionsInterface) {
            return $options;
        }
        if ($options instanceof LoopInterface) {
            return new ClientOptions(array('eventloop' => $options));
        }

        throw new \InvalidArgumentException("Invalid type for client options");
    }

    /**
     * Initializes one or multiple connection (cluster) objects from various
     * types of arguments (string, array) or returns the passed object if it
     * implements Predis\Connection\ConnectionInterface.
     *
     * @param mixed $parameters Connection parameters or instance.
     * @param ClientOptionsInterface $options Client options.
     * @return AsynchronousConnectionInterface
     */
    protected function initializeConnection($parameters, ClientOptionsInterface $options)
    {
        $connection = null;

        if ($parameters instanceof AsynchronousConnectionInterface) {
            $connection = $parameters;

            if ($connection->getEventLoop() !== $this->getEventLoop()) {
                throw new ClientException("Client and connection must share the same event loop instance.");
            }
        }
        else {
            $connection = new AsynchronousConnection($parameters ?: self::DEFAULT_SERVER, $this->getEventLoop());

            if (isset($options->on_connect)) {
                $connection->setConnectCallback($options->on_connect);
            }
            if (isset($options->on_error)) {
                $connection->setErrorCallback($options->on_error);
            }
        }

        return $connection;
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
        return $this->eventLoop;
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
        if (isset($callback)) {
            $this->connection->setConnectCallback($callback);
        }

        $this->connection->connect();
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
     * @return boolean
     */
    public function isConnected()
    {
        return $this->connection->isConnected();
    }

    /**
     * Returns the underlying connection instance.
     *
     * @return AsynchronousConnectionInterface
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
        if (!is_callable($callback = array_pop($arguments))) {
            $arguments[] = $callback;
            $callback = null;
        }

        $command = $this->profile->createCommand($method, $arguments);
        $this->executeCommand($command, $callback);
    }

    /**
     * Creates a new instance of the specified Redis command.
     *
     * @param string $method The name of a Redis command.
     * @param array $arguments The arguments for the command.
     * @return CommandInterface
     */
    public function createCommand($method, $arguments = array())
    {
        return $this->profile->createCommand($method, $arguments);
    }

    /**
     * Executes the specified Redis command.
     *
     * @param CommandInterface $command A Redis command.
     * @param mixed $callback Optional callback.
     */
    public function executeCommand(CommandInterface $command, $callback = null)
    {
        $this->connection->executeCommand($command, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function pipeline(/* arguments */)
    {
        throw new NotSupportedException('Pipelining is not supported with this client');
    }

    /**
     * {@inheritdoc}
     */
    public function multiExec(/* arguments */)
    {
        throw new NotSupportedException('MULTI / EXEC is not supported with this client');
    }

    /**
     * {@inheritdoc}
     */
    public function pubSub(/* arguments */)
    {
        throw new NotSupportedException('Not yet implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function monitor(/* arguments */)
    {
        // TODO: missing actual implementation.
        return $this->__call('monitor', func_get_args());
    }
}
