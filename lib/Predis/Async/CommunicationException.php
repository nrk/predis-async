<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async;

use Predis\PredisException;
use Predis\Async\Connection\ConnectionInterface;

/**
 * Base exception class for asynchronous network-related errors.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
abstract class CommunicationException extends PredisException
{
    private $connection;

    /**
     * @param ConnectionInterface $connection Connection that generated the exception.
     * @param string $message Error message.
     * @param int $code Error code.
     * @param \Exception $innerException Inner exception for wrapping the original error.
     */
    public function __construct(ConnectionInterface $connection,
        $message = null, $code = null, \Exception $innerException = null)
    {
        parent::__construct($message, $code, $innerException);

        $this->connection = $connection;
    }

    /**
     * Gets the connection that generated the exception.
     *
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
