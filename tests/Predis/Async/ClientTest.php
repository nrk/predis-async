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

use Predis\Profile\ServerProfile;
use Predis\Async\Connection\StreamConnection;

/**
 *
 */
class ClientTest extends PredisAsyncTestCase
{
    /**
     * @group disconnected
     */
    public function testConstructorWithoutArguments()
    {
        $client = new Client();

        $connection = $client->getConnection();
        $this->assertInstanceOf('Predis\Async\Connection\ConnectionInterface', $connection);

        $parameters = $connection->getParameters();
        $this->assertSame($parameters->host, '127.0.0.1');
        $this->assertSame($parameters->port, 6379);

        $options = $client->getOptions();
        $this->assertSame($options->profile->getVersion(), ServerProfile::getDefault()->getVersion());

        $this->assertFalse($client->isConnected());
    }

    /**
     * @group disconnected
     */
    public function testConstructorWithNullArgument()
    {
        $client = new Client(null);

        $connection = $client->getConnection();
        $this->assertInstanceOf('Predis\Async\Connection\ConnectionInterface', $connection);

        $parameters = $connection->getParameters();
        $this->assertSame($parameters->host, '127.0.0.1');
        $this->assertSame($parameters->port, 6379);

        $options = $client->getOptions();
        $this->assertSame($options->profile->getVersion(), ServerProfile::getDefault()->getVersion());

        $this->assertFalse($client->isConnected());
    }

    /**
     * @group disconnected
     */
    public function testConstructorWithNullAndNullArguments()
    {
        $client = new Client(null, null);

        $connection = $client->getConnection();
        $this->assertInstanceOf('Predis\Async\Connection\ConnectionInterface', $connection);

        $parameters = $connection->getParameters();
        $this->assertSame($parameters->host, '127.0.0.1');
        $this->assertSame($parameters->port, 6379);

        $options = $client->getOptions();
        $this->assertSame($options->profile->getVersion(), ServerProfile::getDefault()->getVersion());

        $this->assertFalse($client->isConnected());
    }

    /**
     * @group disconnected
     */
    public function testConstructorWithArrayArgument()
    {
        $client = new Client($arg1 = array('host' => 'localhost', 'port' => 7000));

        $parameters = $client->getConnection()->getParameters();
        $this->assertSame($parameters->host, $arg1['host']);
        $this->assertSame($parameters->port, $arg1['port']);
    }

    /**
     * @group disconnected
     */
    public function testConstructorWithStringArgument()
    {
        $client = new Client('tcp://localhost:7000');

        $parameters = $client->getConnection()->getParameters();
        $this->assertSame($parameters->host, 'localhost');
        $this->assertSame($parameters->port, 7000);
    }

    /**
     * @group disconnected
     */
    public function testConstructorWithConnectionArgument()
    {
        $parameters = $this->getParameters();
        $eventloop  = $this->getEventLoop();

        $connection = new StreamConnection($parameters, $eventloop);

        $client = new Client($connection, $eventloop);

        $this->assertInstanceOf('Predis\Async\Connection\ConnectionInterface', $client->getConnection());
        $this->assertSame($connection, $client->getConnection());
        $this->assertSame($connection->getEventLoop(), $client->getEventLoop());

        $parameters = $client->getConnection()->getParameters();
        $this->assertSame($parameters->host, REDIS_SERVER_HOST);
        $this->assertSame($parameters->port, (int) REDIS_SERVER_PORT);
    }

    /**
     * @group disconnected
     * @expectedException Predis\ClientException
     * @expectedExceptionMessage Client and connection must share the same event loop instance
     */
    public function testConnectionAndClientMustShareSameEventLoop()
    {
        $connection = new StreamConnection($this->getParameters(), $this->getEventLoop());
        $client = new Client($connection);
    }

    /**
     * @group disconnected
     * @todo How should we test for the error callback?
     */
    public function testConstructorWithNullAndArrayArgument()
    {
        $options = array(
            'profile'   => '2.0',
            'prefix'    => 'prefix:',
            'eventloop' => $loop = $this->getEventLoop(),
            'on_error'  => $callback = function ($client, $error) { },
        );

        $client = new Client(null, $options);

        $profile = $client->getProfile();
        $this->assertSame($profile->getVersion(), ServerProfile::get('2.0')->getVersion());
        $this->assertInstanceOf('Predis\Command\Processor\KeyPrefixProcessor', $profile->getProcessor());
        $this->assertSame('prefix:', $profile->getProcessor()->getPrefix());

        $this->assertSame($loop, $client->getEventLoop());
    }

    /**
     * @group disconnected
     */
    public function testConstructorWithNullAndEventLoopArgument()
    {
        $client = new Client(null, $loop = $this->getEventLoop());
        $this->assertSame($loop, $client->getEventLoop());
    }

    /**
     * @group disconnected
     */
    public function testConnectAndDisconnect()
    {
        $loop = $this->getEventLoop();
        $callback = function ($client, $connection) { };

        $connection = $this->getMock('Predis\Async\Connection\ConnectionInterface');
        $connection->expects($this->once())->method('getEventLoop')->will($this->returnValue($loop));
        $connection->expects($this->once())->method('connect')->with($callback);
        $connection->expects($this->once())->method('disconnect');

        $client = new Client($connection, $loop);
        $client->connect($callback);
        $client->disconnect();
    }

    /**
     * @group disconnected
     */
    public function testIsConnectedChecksConnectionState()
    {
        $loop = $this->getEventLoop();
        $callback = function ($client, $connection) { };

        $connection = $this->getMock('Predis\Async\Connection\ConnectionInterface');
        $connection->expects($this->once())->method('getEventLoop')->will($this->returnValue($loop));
        $connection->expects($this->once())->method('isConnected');

        $client = new Client($connection, $loop);
        $client->isConnected();
    }

    /**
     * @group disconnected
     */
    public function testCreatesNewCommandUsingSpecifiedProfile()
    {
        $ping = ServerProfile::getDefault()->createCommand('ping', array());

        $profile = $this->getMock('Predis\Profile\ServerProfileInterface');
        $profile->expects($this->once())
                ->method('createCommand')
                ->with('ping', array())
                ->will($this->returnValue($ping));

        $client = new Client(null, array('profile' => $profile));
        $this->assertSame($ping, $client->createCommand('ping', array()));
    }

    /**
     * @group disconnected
     */
    public function testExecuteCommandReturnsParsedRepliesInCallback()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @group disconnected
     */
    public function testExecuteCommandReturnsErrorResponseInCallback()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @group disconnected
     */
    public function testExecuteCommandEvenWithoutCallback()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @group disconnected
     * @expectedException Predis\ClientException
     * @expectedExceptionMessage 'invalidcommand' is not a registered Redis command
     */
    public function testThrowsExceptionOnNonRegisteredRedisCommand()
    {
        $this->getClient()->invalidCommand();
    }

    /**
     * @group disconnected
     */
    public function testPubSubReturnsPubSubContext()
    {
        $client = $this->getClient();
        $pubsub = $client->pubsub(array(), function ($event, $pubsub) {});

        $this->assertInstanceOf('Predis\Async\PubSub\PubSubContext', $pubsub);
        $this->assertFalse($client->isConnected());
    }

    /**
     * @group disconnected
     * @todo The underlying transaction should be lazily initialized.
     */
    public function testMonitorReturnsMonitorContext()
    {
        $client = $this->getClient();
        $monitor = $client->monitor(function ($event, $monitor) {}, false);

        $this->assertInstanceOf('Predis\Async\Monitor\MonitorContext', $monitor);
        $this->assertFalse($client->isConnected());
    }

    /**
     * @group connected
     * @todo The underlying transaction should be lazily initialized.
     */
    public function testMultiExecReturnsMultiExecContext()
    {
        $client = $this->getClient();
        $transaction = $client->multiexec();

        $this->assertInstanceOf('Predis\Async\Transaction\MultiExecContext', $transaction);
        $this->assertTrue($client->isConnected());
    }

    /**
     * @group connected
     */
    public function testCanConnectToRedis()
    {
        $test = $this;
        $trigger = false;

        $parameters = $this->getParameters();
        $options = $this->getOptions();
        $client = new Client($parameters, $options);

        $client->connect(function ($cbkClient, $cbkConnection) use ($test, $client, &$trigger) {
            $trigger = true;

            $test->assertInstanceOf('Predis\Async\Client', $cbkClient);
            $test->assertInstanceOf('Predis\Async\Connection\ConnectionInterface', $cbkConnection);

            $test->assertSame($client, $cbkClient);

            $client->disconnect();
        });

        $loop = $client->getEventLoop();

        $loop->addTimer(0.01, function () use ($test, &$trigger) {
            $test->assertTrue($trigger, 'The client was unable to connect to Redis');
        });

        $loop->run();
    }

    /**
     * @group connected
     */
    public function testCanSendCommandsToRedis()
    {
        $this->withConnectedClient(function ($test, $client) {
            $client->echo('Predis\Async', function ($reply, $client, $command) use ($test) {
                $test->assertInstanceOf('Predis\Async\Client', $client);
                $test->assertInstanceOf('Predis\Command\CommandInterface', $command);
                $test->assertSame('Predis\Async', $reply);

                $client->disconnect();
            });
        });
    }
}
