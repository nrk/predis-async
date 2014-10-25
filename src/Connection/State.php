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
use RuntimeException;

/**
 * Class used to track connection states.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class State
{
    const DISCONNECTED   = 1;     // 0b00000001
    const CONNECTING     = 2;     // 0b00000010
    const CONNECTED      = 4;     // 0b00000100
    const STREAM_CONTEXT = 8;     // 0b00001000

    const MULTIEXEC      = 20;    // 0b00010100
    const MONITOR        = 40;    // 0b00101000
    const PUBSUB         = 72;    // 0b01001000

    protected $processCallback;
    protected $streamCallback;

    protected $state = self::DISCONNECTED;

    /**
     * Sets the current state value using the specified flags.
     *
     * @param int $flags Flags.
     */
    protected function setFlags($flags)
    {
        $this->state = $flags;
    }

    /**
     * Returns the current state value.
     *
     * @return int
     */
    protected function getFlags()
    {
        return $this->state;
    }

    /**
     * Checks if the specified flags are set in the current state value.
     *
     * @param int $flags Flags.
     *
     * @return bool
     */
    protected function checkFlags($flags)
    {
        return ($this->state & $flags) === $flags;
    }

    /**
     * Sets the specified flags in the current state value.
     *
     * @param int $flags Flags.
     */
    protected function flag($flags)
    {
        $this->state |= $flags;
    }

    /**
     * Unsets the specified flags from the current state value.
     *
     * @param int $flags Flags.
     */
    protected function unflag($flags)
    {
        $this->state &= ~$flags;
    }

    /**
     * Switches the internal state to one of the supported states.
     *
     * @param int $context State flag.
     */
    public function setState($state)
    {
        $state &= ~248; // 0b11111000

        if (($state & ($state - 1)) !== 0) {
            throw new InvalidArgumentException("State must be a valid state value");
        }

        $this->setFlags($state);
        $this->streamCallback = null;
    }

    /**
     * Switches the internal state from a context to CONNECTED.
     */
    public function clearStreamingContext()
    {
        $this->setFlags(self::CONNECTED);
        $this->streamCallback = null;
    }

    /**
     * Switches the internal state to one of the supported Redis contexts and
     * associates a callback to process streaming reply items.
     *
     * @param int   $context  Context flag.
     * @param mixed $callback Callable object.
     */
    public function setStreamingContext($context, $callback)
    {
        if (0 === $context &= ~7) { // 0b00000111
            throw new InvalidArgumentException("Context must be a valid context value");
        }

        if (!is_callable($callback)) {
            throw new InvalidArgumentException("Callback must be a valid callable object");
        }

        $this->setFlags($context);
        $this->streamCallback = $callback;
    }

    /**
     * Sets the callback used to handle responses in the CONNECTED state.
     *
     * @param mixed $callback Callable object.
     */
    public function setProcessCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException("Callback must be a valid callable object");
        }

        $this->processCallback = $callback;
    }

    /**
     * Processes a response depending on the current state.
     *
     * @param mixed $response Response returned from the server.
     *
     * @return mixed
     */
    public function process($response)
    {
        if ($this->checkFlags(self::CONNECTED)) {
            return call_user_func($this->processCallback, $this, $response);
        }

        if ($this->checkFlags(self::STREAM_CONTEXT)) {
            return call_user_func($this->streamCallback, $this, $response);
        }

        // TODO: we should handle invalid states in a different manner.
        throw new RuntimeException("Invalid connection state: $this");
    }

    /**
     * Returns a string with a mnemonic representation of the current state.
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->checkFlags(self::DISCONNECTED)) {
            return 'DISCONNECTED';
        }

        if ($this->checkFlags(self::CONNECTING)) {
            return 'CONNECTING';
        }

        if ($this->checkFlags(self::CONNECTED)) {
            return 'CONNECTED';
        }

        if ($this->checkFlags(self::MULTIEXEC)) {
            return '[CONTEXT] MULTI/EXEC';
        }

        if ($this->checkFlags(self::PUBSUB)) {
            return '[CONTEXT] PUB/SUB';
        }

        if ($this->checkFlags(self::MONITOR)) {
            return '[CONTEXT] MONITOR';
        }

        return 'UNKNOWN';
    }
}
