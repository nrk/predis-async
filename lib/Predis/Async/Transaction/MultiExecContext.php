<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async\Transaction;

use RuntimeException;
use SplQueue;
use Predis\ResponseObjectInterface;
use Predis\ResponseQueued;
use Predis\Async\Client;
use Predis\Async\Connection\ConnectionInterface;
use Predis\Async\Connection\State;

/**
 * Class offering an abstraction for MULTI / EXEC transactions.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class MultiExecContext
{
    protected $client;

    /**
     * Creates a new transaction object.
     *
     * @param Client $client Client instance.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->commands = new SplQueue();

        $this->initialize();
    }

    /**
     * Initializes a new MULTI / EXEC transaction on the server
     * by issuing the MULTI command to Redis.
     */
    protected function initialize()
    {
        $command = $this->client->createCommand('MULTI');

        $this->client->executeCommand($command, function ($response) {
            if (false === $response) {
                throw new RuntimeException('Could not initialize a MULTI / EXEC transaction');
            }
        });
    }

    /**
     * Dinamically invokes a Redis command with the specified arguments.
     *
     * @param string $method Command ID.
     * @param array $arguments Arguments for the command.
     * @return MultiExecContext
     */
    public function __call($method, $arguments)
    {
        $commands = $this->commands;
        $command = $this->client->createCommand($method, $arguments);

        $this->client->executeCommand($command, function ($response, $command) use ($commands) {
            if (false === $response instanceof ResponseQueued) {
                throw new RuntimeException('Unexpected response in MULTI / EXEC [expected +QUEUED]');
            }

            $commands->enqueue($command);
        });

        return $this;
    }

    /**
     * Commits the transaction by issuing the EXEC command to Redis and
     * parses the array of replies before passing it to the callback.
     *
     * @param mixed $callback Callback invoked after execution.
     */
    public function execute($callback)
    {
        $commands = $this->commands;
        $command  = $this->client->createCommand('EXEC');

        $this->client->executeCommand($command, function ($responses, $_, $client) use ($commands, $callback) {
            $size = count($responses);
            $processed = array();

            for ($i = 0; $i < $size; $i++) {
                $command  = $commands->dequeue();
                $response = $responses[$i];

                unset($responses[$i]);

                if (false === $response instanceof ResponseObjectInterface) {
                    $response = $command->parseResponse($response);
                }

                $processed[$i] = $response;
            }

            call_user_func($callback, $processed, $client);
        });
    }

    /**
     * This method is an alias for execute().
     *
     * @param mixed $callback Callback invoked after execution.
     */
    public function exec($callback)
    {
        $this->execute($callback);
    }
}
