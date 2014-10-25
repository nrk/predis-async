<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async\Monitor;

use InvalidArgumentException;
use Predis\Async\Client;

/**
 * Redis MONITOR consumer abstraction.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class Consumer
{
    protected $client;
    protected $callback;

    /**
     * @param Client $client   Client instance.
     * @param mixed  $callback Callback invoked on each received message.
     */
    public function __construct(Client $client, $callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Callback must be a valid callable object');
        }

        $this->client = $client;
        $this->callback = $callback;
    }

    /**
     * Parses the response string returned by the server into an object.
     *
     * @param string $payload Payload string.
     *
     * @return object
     */
    protected function parsePayload($payload)
    {
        $database = 0;
        $client = null;

        $pregCallback = function ($matches) use (&$database, &$client) {
            if (2 === $count = count($matches)) {
                // Redis <= 2.4
                $database = (int) $matches[1];
            }

            if (4 === $count) {
                // Redis >= 2.6
                $database = (int) $matches[2];
                $client = $matches[3];
            }

            return ' ';
        };

        $event = preg_replace_callback('/ \(db (\d+)\) | \[(\d+) (.*?)\] /', $pregCallback, $payload, 1);
        @list($timestamp, $command, $arguments) = explode(' ', $event, 3);

        return (object) [
            'timestamp' => (float) $timestamp,
            'database'  => $database,
            'client'    => $client,
            'command'   => substr($command, 1, -1),
            'arguments' => $arguments,
        ];
    }

    /**
     * Wraps the user-provided callback to process payloads returned by the server.
     *
     * @param string           $payload Payload returned by the server.
     * @param Client           $client  Associated client instance.
     * @param CommandInterface $command Command instance (always NULL in case of streaming contexts).
     */
    public function __invoke($payload, $client, $command)
    {
        $parsedPayload = $this->parsePayload($payload);
        call_user_func($this->callback, $parsedPayload, $this);
    }

    /**
     * Initializes the consumer and sends the MONITOR command to the server.
     */
    public function start()
    {
        $command = $this->client->createCommand('MONITOR');
        $this->client->executeCommand($command, $this);
    }

    /**
     * Stops the consumer. Internally this is done by disconnecting from server
     * since there is no way to terminate the stream initialized by MONITOR.
     */
    public function stop()
    {
        $this->client->disconnect();
    }

    /**
     * Returns the underlying client instance.
     *
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }
}
