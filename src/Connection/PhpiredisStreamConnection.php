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
use Predis\Response\Status as StatusResponse;
use Predis\Response\Error as ErrorResponse;
use React\EventLoop\LoopInterface;

class PhpiredisStreamConnection extends AbstractConnection
{
    protected $reader;

    /**
     * {@inheritdoc}
     */
    public function __construct(ParametersInterface $parameters, LoopInterface $loop)
    {
        parent::__construct($parameters, $loop);

        $this->initializeReader();
    }

    /**
     * {@inheritdoc}
     */
    public function __destruct()
    {
        phpiredis_reader_destroy($this->reader);

        parent::__destruct();
    }

    /**
     * Initializes the protocol reader resource.
     */
    protected function initializeReader()
    {
        $this->reader = phpiredis_reader_create();

        phpiredis_reader_set_status_handler($this->reader, $this->getStatusHandler());
        phpiredis_reader_set_error_handler($this->reader, $this->getErrorHandler());
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
     *
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
}
