<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async\Connection;

use InvalidArgumentException;
use SplQueue;
use Predis\ClientException;
use Predis\Command\CommandInterface;
use Predis\Connection\ParametersInterface;
use Predis\Response\Status as StatusResponse;
use Predis\Response\Error as ErrorResponse;
use Predis\Async\Buffer\StringBuffer;
use React\EventLoop\LoopInterface;

class PhpiredisStreamConnection implements ConnectionInterface
{
    protected $parameters;
    protected $loop;
    protected $socket;
    protected $reader;
    protected $buffer;
    protected $commands;
    protected $state;
    protected $timeout = null;
    protected $errorCallback = null;
    protected $readableCallback = null;
    protected $writableCallback = null;

    /**
     * @param ParametersInterface $parameters
     * @param LoopInterface $loop
     */
    public function __construct(ParametersInterface $parameters, LoopInterface $loop)
    {
        $this->parameters = $parameters;
        $this->loop = $loop;

        $this->buffer = new StringBuffer();
        $this->commands = new SplQueue();
        $this->readableCallback = array($this, 'read');
        $this->writableCallback = array($this, 'write');

        $this->state = new State();
        $this->state->setProcessCallback($this->getProcessCallback());

        $this->initializeReader();
    }

    /**
     * Disconnects from the server and destroys the underlying resource when
     * PHP's garbage collector kicks in.
     */
    public function __destruct()
    {
        phpiredis_reader_destroy($this->reader);

        if ($this->isConnected()) {
           $this->disconnect();
        }
    }

    /**
     * Initializes the protocol reader resource.
     */
    protected function initializeReader()
    {
        $reader = phpiredis_reader_create();

        phpiredis_reader_set_status_handler($reader, $this->getStatusHandler());
        phpiredis_reader_set_error_handler($reader, $this->getErrorHandler());

        $this->reader = $reader;
    }

    /**
     * Returns the callback used to handle commands and firing callbacks depending
     * on the current state of the connection to Redis.
     *
     * @return mixed
     */
    protected function getProcessCallback()
    {
        $connection = $this;
        $commands = $this->commands;
        $streamingWrapper = $this->getStreamingWrapperCreator();

        return function ($state, $response) use ($commands, $connection, $streamingWrapper) {
            list($command, $callback) = $commands->dequeue();

            switch ($command->getId()) {
                case 'SUBSCRIBE':
                case 'PSUBSCRIBE':
                    $callback = $streamingWrapper($connection, $callback);
                    $state->setStreamingContext(State::PUBSUB, $callback);
                    break;

                case 'MONITOR':
                    $callback = $streamingWrapper($connection, $callback);
                    $state->setStreamingContext(State::MONITOR, $callback);
                    break;

                case 'MULTI':
                    $state->setState(State::MULTIEXEC);
                    goto process;

                case 'EXEC':
                case 'DISCARD':
                    $state->setState(State::CONNECTED);
                    goto process;

                default:
                process:
                    call_user_func($callback, $response, $connection, $command);
                    break;
            }
        };
    }

    /**
     * Returns a wrapper to the user-provided callback passed to handle response chunks
     * streamed down by replies to commands such as MONITOR, SUBSCRIBE and PSUBSCRIBE.
     *
     * @return mixed
     */
    protected function getStreamingWrapperCreator()
    {
        return function ($connection, $callback) {
            return function ($state, $response) use ($connection, $callback) {
                call_user_func($callback, $response, $connection, null);
            };
        };
    }

    /**
     * Creates the underlying resource used to communicate with Redis.
     *
     * @return mixed
     */
    protected function createResource($connectCallback = null)
    {
        $connection = $this;
        $parameters = $this->parameters;

        $uri = "$parameters->scheme://".($parameters->scheme === 'unix' ? $parameters->path : "$parameters->host:$parameters->port");
        $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;

        if (!$socket = @stream_socket_client($uri, $errno, $errstr, 0, $flags)) {
            return $this->onError(new ConnectionException($this, trim($errstr), $errno));
        }

        stream_set_blocking($socket, 0);

        $this->state->setState(State::CONNECTING);

        $this->loop->addWriteStream($socket, function ($socket) use ($connection, $connectCallback) {
            if ($connection->onConnect()) {
                if (isset($connectCallback)) {
                    call_user_func($connectCallback, $connection);
                }

                $connection->write();
            }
        });

        $this->timeout = $this->armTimeoutMonitor($this->parameters->timeout, $this->errorCallback);

        return $socket;
    }

    /**
     * Creates the underlying resource used to communicate with Redis.
     *
     * @param int $timeout Timeout in seconds
     * @param mixed $callback Callback invoked on timeout.
     */
    protected function armTimeoutMonitor($timeout, $callback)
    {
        $timer = $this->loop->addTimer($timeout, function ($timer) {
            list($connection, $callback) = $timer->getData();

            $connection->disconnect();

            if (isset($callback)) {
                call_user_func($callback, $connection, new ConnectionException($connection, 'Connection timed out'));
            }
        });

        $timer->setData(array($this, $callback));

        return $timer;
    }

    /**
     * Disarm the timer used to monitor a connect() timeout is set.
     */
    protected function disarmTimeoutMonitor()
    {
        if (isset($this->timeout)) {
            $this->timeout->cancel();
            $this->timeout = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected()
    {
        return isset($this->socket) && stream_socket_get_name($this->socket, true) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function connect($callback)
    {
        if (!$this->isConnected()) {
            $this->socket = $this->createResource($callback);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        $this->disarmTimeoutMonitor();

        if (isset($this->socket)) {
            $this->loop->removeStream($this->socket);
            $this->state->setState(State::DISCONNECTED);
            $this->buffer->reset();

            unset($this->socket);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResource()
    {
        if (isset($this->socket)) {
            return $this->socket;
        }

        $this->socket = $this->createResource();

        return $this->socket;
    }

    /**
     * {@inheritdoc}
     */
    public function setErrorCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('The specified callback must be a callable object');
        }

        $this->errorCallback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function onConnect()
    {
        $socket = $this->getResource();

        // The following one is a terrible hack but it looks like this is the only way to
        // detect connection refused errors with PHP's stream sockets. Blame PHP as usual.
        if (stream_socket_get_name($socket, true) === false) {
            return $this->onError(new ConnectionException($this, "Connection refused"));
        }

        $this->state->setState(State::CONNECTED);
        $this->disarmTimeoutMonitor();

        $this->loop->removeWriteStream($socket);
        $this->loop->addReadStream($socket, $this->readableCallback);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function onError(\Exception $exception)
    {
        $this->disconnect();

        if (isset($this->errorCallback)) {
            call_user_func($this->errorCallback, $this, $exception);
        }

        return false;
    }

    /**
     * Gets the handler used by the protocol reader to handle status replies.
     *
     * @return \Closure
     */
    protected function getStatusHandler()
    {
        return function ($payload) {
            return StatusResponse::get($payload);
        };
    }

    /**
     * Gets the handler used by the protocol reader to handle Redis errors.
     *
     * @param Boolean $throw_errors Specify if Redis errors throw exceptions.
     * @return \Closure
     */
    protected function getErrorHandler()
    {
        return function ($errorMessage) {
            return new ErrorResponse($errorMessage);
        };
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventLoop()
    {
        return $this->loop;
    }

    /**
     * Gets an identifier for the connection.
     *
     * @return string
     */
    protected function getIdentifier()
    {
        if ($this->parameters->scheme === 'unix') {
            return $this->parameters->path;
        }

        return "{$this->parameters->host}:{$this->parameters->port}";
    }

    /**
     * {@inheritdoc}
     */
    public function write()
    {
        $socket = $this->getResource();

        if ($this->buffer->isEmpty()) {
            $this->loop->removeWriteStream($socket);
            return;
        }

        $buffer = $this->buffer->read(4096);

        if (-1 === $ret = @stream_socket_sendto($socket, $buffer)) {
            return $this->onError(new ConnectionException($this, 'Error while writing bytes to the server'));
        }

        $this->buffer->discard(min($ret, strlen($buffer)));
    }

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        $buffer = stream_socket_recvfrom($this->getResource(), 4096);

        if ($buffer === false || $buffer === '') {
            return $this->onError(new ConnectionException($this, 'Error while reading bytes from the server'));
        }

        phpiredis_reader_feed($reader = $this->reader, $buffer);

        while (phpiredis_reader_get_state($reader) === PHPIREDIS_READER_STATE_COMPLETE) {
            $this->state->process(phpiredis_reader_get_reply($reader));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function executeCommand(CommandInterface $command, $callback)
    {
        $cmdargs = $command->getArguments();
        array_unshift($cmdargs, $command->getId());

        if ($this->buffer->isEmpty()) {
            $this->loop->addWriteStream($this->getResource(), $this->writableCallback);
        }

        $this->buffer->append(phpiredis_format_command($cmdargs));
        $this->commands->enqueue(array($command, $callback));
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->getIdentifier();
    }
}
