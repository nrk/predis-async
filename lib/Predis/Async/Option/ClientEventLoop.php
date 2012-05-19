<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async\Option;

use Predis\Option\ClientOptionsInterface;
use Predis\Option\AbstractOption;
use React\EventLoop\LoopInterface;
use React\EventLoop\LibEventLoop;
use React\EventLoop\StreamSelectLoop;

/**
 * Option class that handles the creation of the event loop.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class ClientEventLoop extends AbstractOption
{
    /**
     * {@inheritdoc}
     */
    public function filter(ClientOptionsInterface $options, $value)
    {
        if (!$value instanceof LoopInterface) {
            throw new \InvalidArgumentException('Invalid value for the eventloop option');
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefault(ClientOptionsInterface $options)
    {
        return new StreamSelectLoop();
    }
}
