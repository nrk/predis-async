<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async\Buffer;

/**
 * Simple string buffer class.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class StringBuffer
{
    private $buffer;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->buffer = '';
    }

    /**
     * {@inheritdoc}
     */
    public function read($length)
    {
        if (false === $buffer = substr($this->buffer, 0, $length)) {
            return '';
        }

        return $buffer;
    }

    /**
     * {@inheritdoc}
     */
    public function consume($length)
    {
        if ('' !== $buffer = $this->read($length)) {
            $this->buffer = substr($this->buffer, strlen($buffer)) ?: '';
        }

        return $buffer;
    }

    /**
     * {@inheritdoc}
     */
    public function discard($length)
    {
        $this->buffer = substr($this->buffer, $length) ?: '';

        return $length;
    }

    /**
     * {@inheritdoc}
     */
    public function append($buffer)
    {
        $this->buffer .= $buffer;

        return strlen($buffer);
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return $this->buffer === '';
    }

    /**
     * {@inheritdoc}
     */
    public function length()
    {
        return strlen($this->buffer);
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->buffer = '';
    }
}
