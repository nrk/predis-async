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
use Predis\Configuration\OptionsInterface;
use Predis\Connection\Parameters;
use Predis\Connection\ParametersInterface;
use Predis\Command\CommandInterface;
use Predis\Response\ResponseInterface;
use Predis\Async\Connection\ConnectionInterface;
use Predis\Async\Connection\PhpiredisStreamConnection;
use Predis\Async\Connection\StreamConnection;
use React\EventLoop\LoopInterface;

/**
 *  Client class used for connecting and executing commands on Redis.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class Client
{
    const VERSION = '0.3.0-dev';

    private $profile;
    protected $connection;

    /**
     * @param mixed $parameters Connection parameters.
     * @param mixed $options    Options to configure some behaviours of the client.
     */
    public function __construct($parameters = null, $options = null)
    {
        $this->options = $this->createOptions($options ?: []);
        $this->connection = $this->createConnection($parameters, $this->options);
        $this->profile = $this->options->profile;
    }

    /**
     * Creates an instance of Predis\Async\Configuration\Options from different
     * types of arguments or simply returns the passed argument if it is an
     * instance of Predis\Configuration\OptionsInterface.
     *
     * @param mixed $options Client options.
     *
     * @return OptionsInterface
     */
    protected function createOptions($options)
    {
        if (is_array($options)) {
            return new Configuration\Options($options);
        }

        if ($options instanceof LoopInterface) {
            return new Configuration\Options(['eventloop' => $options]);
        }

        if ($options instanceof OptionsInterface) {
            return $options;
        }

        throw new \InvalidArgumentException('Invalid type for client options');
    }

    /**
     * Creates an instance of connection parameters.
     *
     * @param mixed $parameters Connection parameters.
     *
     * @return ParametersInterface
     */
    protected function createParameters($parameters)
    {
        if ($parameters instanceof ParametersInterface) {
            return $parameters;
        }

        return Parameters::create($parameters);
    }

    /**
     * Initializes a connection from various types of arguments or returns the
     * passed object if it implements Predis\Connection\ConnectionInterface.
     *
     * @param mixed            $parameters Connection parameters or instance.
     * @param OptionsInterface $options    Client options.
     *
     * @return ConnectionInterface
     */
    protected function createConnection($parameters, OptionsInterface $options)
    {
        if ($parameters instanceof ConnectionInterface) {
            if ($parameters->getEventLoop() !== $this->options->eventloop) {
                throw new ClientException('Client and connection must share the same event loop.');
            }

            return $parameters;
        }

        $eventloop = $this->options->eventloop;
        $parameters = $this->createParameters($parameters);

        if ($options->phpiredis) {
            $connection = new PhpiredisStreamConnection($eventloop, $parameters);
        } else {
            $connection = new StreamConnection($eventloop, $parameters);
        }

        if (isset($options->on_error)) {
            $this->setErrorCallback($connection, $options->on_error);
        }

        return $connection;
    }

    /**
     * Sets the callback used to notify the client about connection errors.
     *
     * @param ConnectionInterface $connection Connection instance.
     * @param mixed               $callback   Callback for error event.
     */
    protected function setErrorCallback(ConnectionInterface $connection, $callback)
    {
        $connection->setErrorCallback(function ($connection, $exception) use ($callback) {
            call_user_func($callback, $this, $exception, $connection);
        });
    }

    /**
     * Returns the client options specified upon initialization.
     *
     * @return OptionsInterface
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Returns the server profile used by the client.
     *
     * @return Predis\Profile\ProfileInterface;
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
     * Opens the connection to the server.
     *
     * @param mixed $callback Callback for connection event.
     */
    public function connect($callback)
    {
        $this->connection->connect(function ($connection) use ($callback) {
            call_user_func($callback, $this, $connection);
        });
    }

    /**
     * Closes the underlying connection from the server.
     */
    public function disconnect()
    {
        $this->connection->disconnect();
    }

    /**
     * Returns the current state of the underlying connection.
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
     * Creates a Redis command with the specified arguments and sends a request
     * to the server.
     *
     * @param string $method    Command ID.
     * @param array  $arguments Arguments for the command.
     *
     * @return mixed
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
     * @param string $method    Command ID.
     * @param array  $arguments Arguments for the command.
     *
     * @return CommandInterface
     */
    public function createCommand($method, $arguments = [])
    {
        return $this->profile->createCommand($method, $arguments);
    }

    /**
     * Executes the specified Redis command.
     *
     * @param CommandInterface $command  Command instance.
     * @param mixed            $callback Optional command callback.
     */
    public function executeCommand(CommandInterface $command, $callback = null)
    {
        $this->connection->executeCommand($command, $this->wrapCallback($callback));
    }

    /**
     * Wraps a command callback used to parse the raw response by adding more
     * arguments that will be passed back to user code.
     *
     * @param mixed $callback Command callback.
     */
    protected function wrapCallback($callback)
    {
        return function ($response, $connection, $command) use ($callback) {
            if (!isset($callback)) {
                return;
            }

            if (isset($command) && !$response instanceof ResponseInterface) {
                $response = $command->parseResponse($response);
            }

            call_user_func($callback, $response, $this, $command);
        };
    }

    /**
     * Creates a new transaction context.
     *
     * @return MultiExec
     */
    public function transaction(/* arguments */)
    {
        return new Transaction\MultiExec($this);
    }

    /**
     * Creates a new monitor consumer.
     *
     * @param mixed $callback  Callback invoked on each payload message.
     * @param bool  $autostart Flag indicating if the consumer should be auto-started.
     *
     * @return Monitor\Consumer
     */
    public function monitor($callback, $autostart = true)
    {
        $monitor = new Monitor\Consumer($this, $callback);

        if ($autostart) {
            $monitor->start();
        }

        return $monitor;
    }

    /**
     * Creates a new pub/sub consumer.
     *
     * @param mixed $channels List of channels for subscription.
     * @param mixed $callback Callback invoked on each payload message.
     *
     * @return PubSub\Consumer
     */
    public function pubSubLoop($channels, $callback)
    {
        $pubsub = new PubSub\Consumer($this, $callback);

        if (is_string($channels)) {
            $channels = ['subscribe' => [$channels]];
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
