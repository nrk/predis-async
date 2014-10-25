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
use Predis\Connection\ParametersInterface;
use Predis\Response\Error as ErrorResponse;
use Predis\Response\Status as StatusResponse;
use Clue\Redis\Protocol\Model\StatusReply;
use Clue\Redis\Protocol\Model\ErrorReply;
use Clue\Redis\Protocol\Parser\ResponseParser;
use Clue\Redis\Protocol\Serializer\RecursiveSerializer;
use React\EventLoop\LoopInterface;

class StreamConnection extends AbstractConnection
{
    protected $parser;
    protected $serializer;

    /**
     * {@inheritdoc}
     */
    public function __construct(LoopInterface $loop, ParametersInterface $parameters)
    {
        parent::__construct($loop, $parameters);

        $this->initializeResponseParser();
        $this->initializeRequestSerializer();
    }

    /**
     * Initializes the response parser instance.
     */
    protected function initializeResponseParser()
    {
        $this->parser = new ResponseParser();
    }

    /**
     * Initializes the request serializer instance.
     */
    protected function initializeRequestSerializer()
    {
        $this->serializer = new RecursiveSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function parseResponseBuffer($buffer)
    {
        foreach ($this->parser->pushIncoming($buffer) as $response) {
            $value = $response->getValueNative();

            if ($response instanceof StatusReply) {
                $response = StatusResponse::get($value);
            } elseif ($response instanceof ErrorReply) {
                $response = new ErrorResponse($value);
            } else {
                $response = $value;
            }

            $this->state->process($response);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function executeCommand(CommandInterface $command, callable $callback)
    {
        if ($this->buffer->isEmpty()) {
            $this->loop->addWriteStream($this->getResource(), $this->writableCallback);
        }

        $request = $this->serializer->getRequestMessage($command->getId(), $command->getArguments());

        $this->buffer->append($request);
        $this->commands->enqueue([$command, $callback]);
    }
}
