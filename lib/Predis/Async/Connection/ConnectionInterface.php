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

use Predis\Command\CommandInterface;
use Predis\Connection\ConnectionParametersInterface;
use React\EventLoop\LoopInterface;

/**
 * Defines a connection object used to communicate asynchronously with
 * a single Redis server.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
interface ConnectionInterface
{
    /**
     * Opens the connection.
     *
     * @param mixed $callback Callable object invoked when connect succeeds.
     */
    public function connect($callback);

    /**
     * Closes the connection.
     */
    public function disconnect();

    /**
     * Returns if the connection is open.
     *
     * @return Boolean
     */
    public function isConnected();

    /**
     * Returns the underlying resource used to communicate with a Redis server.
     *
     * @return mixed
     */
    public function getResource();

    /**
     * Gets the parameters used to initialize the connection object.
     *
     * @return ConnectionParametersInterface
     */
    public function getParameters();

    /**
     * Executes a command on Redis and calls the provided callback when the
     * response has been read from the server.
     *
     * @param CommandInterface $command Redis command.
     * @param mixed $callback Callable object.
     */
    public function executeCommand(CommandInterface $command, $callback);

    /**
     * Write the buffer to writable network streams.
     *
     * @return mixed
     */
    public function write();

    /**
     * Read replies from readable network streams.
     *
     * @return mixed
     */
    public function read();

    /**
     * Returns a string representation of the connection.
     *
     * @return string
     */
    public function __toString();
}
