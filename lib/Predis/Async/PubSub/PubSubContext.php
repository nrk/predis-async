<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async\PubSub;

use InvalidArgumentException;
use RuntimeException;
use Predis\Helpers;
use Predis\ResponseObjectInterface;
use Predis\Async\Client;
use Predis\Async\Connection\ConnectionInterface;

/**
 * Class offering an abstraction for a PUB/SUB context.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class PubSubContext
{
    const SUBSCRIBE    = 'subscribe';
    const UNSUBSCRIBE  = 'unsubscribe';
    const PSUBSCRIBE   = 'psubscribe';
    const PUNSUBSCRIBE = 'punsubscribe';
    const MESSAGE      = 'message';
    const PMESSAGE     = 'pmessage';

    protected $client;
    protected $callback;
    protected $closing;

    /**
     * Creates a new PUB/SUB context object.
     *
     * @param Client $client Client instance.
     * @param mixed $callback Callable object.
     */
    public function __construct(Client $client, $callback)
    {
        if (false === is_callable($callback)) {
            throw new InvalidArgumentException('Callback must be a valid callable object');
        }

        $this->client = $client;
        $this->callback = $callback;
        $this->closing = false;
    }

    /**
     * Parses the payload array returned by the server into an object.
     *
     * @param array $payload Payload string.
     * @return object
     */
    protected function parsePayload($payload)
    {
        if ($payload instanceof ResponseObjectInterface) {
            return $payload;
        }

        // TODO: I don't exactly like how we are handling this condition.
        if (true === $this->closing) {
            return null;
        }

        switch ($payload[0]) {
            case self::SUBSCRIBE:
            case self::UNSUBSCRIBE:
            case self::PSUBSCRIBE:
            case self::PUNSUBSCRIBE:
                if ($payload[2] === 0) {
                    $this->closing = true;
                }
                return null;

            case self::MESSAGE:
                return (object) array(
                    'kind'    => $payload[0],
                    'channel' => $payload[1],
                    'payload' => $payload[2],
                );

            case self::PMESSAGE:
                return (object) array(
                    'kind'    => $payload[0],
                    'pattern' => $payload[1],
                    'channel' => $payload[2],
                    'payload' => $payload[3],
                );

            default:
                throw new RuntimeException(
                    "Received an unknown message type {$payload[0]} inside of a pubsub context"
                );
        }
    }

    /**
     * Closes the underlying connection to the server.
     */
    public function quit()
    {
        $this->closing = true;
        $this->client->quit();
    }

    /**
     * {@inheritdoc}
     */
    protected function writeCommand($method, $arguments, $callback = null)
    {
        $arguments = Helpers::filterArrayArguments($arguments ?: array());
        $command = $this->client->createCommand($method, $arguments);

        $this->client->executeCommand($command, $callback);
    }

    /**
     * Subscribes to one or more channels.
     *
     * @param mixed ... List of channels.
     */
    public function subscribe(/* channels */)
    {
        $this->writeCommand('subscribe', func_get_args(), $this);
    }

    /**
     * Subscribes to one or more channels by pattern.
     *
     * @param mixed ... List of pattenrs.
     */
    public function psubscribe(/* channels */)
    {
        $this->writeCommand('psubscribe', func_get_args(), $this);
    }

    /**
     * Unsubscribes from one or more channels.
     *
     * @param mixed ... $channels List of channels.
     */
    public function unsubscribe(/* channels */)
    {
        $this->writeCommand('unsubscribe', func_get_args());
    }

    /**
     * Unsubscribes from one or more channels by pattern.
     *
     * @param mixed ... List of patterns..
     */
    public function punsubscribe(/* channels */)
    {
        $this->writeCommand('punsubscribe', func_get_args());
    }

    /**
     * Wraps the user-provided callback to process payloads returned by the server.
     *
     * @param string $payload Payload returned by the server.
     * @param mixed $command Command instance (always NULL in case of streaming contexts).
     * @param Client $client Associated client instance.
     */
    public function __invoke($payload, $command, $client)
    {
        $parsedPayload = $this->parsePayload($payload);

        if ($this->closing) {
            $this->client->disconnect();
            $this->closing = false;

            return;
        }

        if (isset($parsedPayload)) {
            call_user_func($this->callback, $parsedPayload, $this);
        }
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
