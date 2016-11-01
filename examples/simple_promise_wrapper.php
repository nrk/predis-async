<?php

require_once 'vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$cache = new CacheRedis($loop, [
    'connectionString' => 'tcp://127.0.0.1:6379',
]);
$cache->set('mykey', 'myvalue', 1)->then(function(){
    print 'Set key successfull for 1 seconds lifetime' . PHP_EOL;
}, function($reason){
    print 'Set key failed (' . $reason . ')' . PHP_EOL;
})->then(function() use($cache){
    return $cache->get('mykey');
})->then(function($response){
    print 'Got key value: ' . $response . PHP_EOL;
}, function($reason){
    print 'Cannot get key value: ' . $reason . PHP_EOL;
})->then(function() use($loop, $cache){
    print "Trying to get value after 2 seconds" . PHP_EOL;
    $loop->addTimer(2, function() use($cache){
        $cache->get('mykey')->then(function($response){
            print "Got value from redis after 2 seconds: " . $response . '(isnull: ' . ((int)is_null($response)) . ')' . PHP_EOL;
        }, function($reason){
            print 'Cannot get key value after 2 seconds: ' . $reason . PHP_EOL;
        });
    });
});

$loop->run();

class CacheRedis{
    /**
     * @var array connection options
     */
    public $options;

    /**
     * @var \React\EventLoop\LoopInterface $loop
     */
    public $loop;

    /**
     * @var \Predis\Async\Client $handle
     */
    public $client;

    /**
     * @var \React\Promise\Deferred
     */
    public $deferredConnect;

    /**
     * CacheRedis constructor.
     * @param \React\EventLoop\LoopInterface $loop
     * @param array $options [connectionString => ...]
     * @throws Exception
     */
    public function __construct(\React\EventLoop\LoopInterface $loop, $options)
    {

        if(!isset($options['connectionString'])){
            throw new Exception('Cannot create CacheRedis without connectionString');
        }

        $this->loop = $loop;
        $this->options = $options;

        $clientOptions = new \Predis\Async\Configuration\Options([
            'eventloop' => $this->loop,
        ]);
        /** @noinspection PhpUndefinedFieldInspection */
        $clientOptions->on_error = function(){
            if(!is_null($this->deferredConnect)){
                $this->deferredConnect->reject('Cannot connect to Redis servers');
                $this->deferredConnect = null;
                return;
            }
            throw new Exception('Got unknown error when working with redis');
        };

        $this->client = new \Predis\Async\Client($this->options['connectionString'], $clientOptions);
    }

    /**
     * Connect to server if not connected
     * @return \React\Promise\Promise
     */
    public function getConnection(){
        if($this->client->isConnected()){
            return \React\Promise\resolve($this->client);
        }
        if(!is_null($this->deferredConnect)){
            return $this->deferredConnect->promise();
        }
        $this->deferredConnect = new \React\Promise\Deferred();
        $this->client->connect(function(){
            $this->deferredConnect->resolve($this->client);
            $this->deferredConnect = null;
        });
        return $this->deferredConnect->promise();
    }

    /**
     * Set key to value with lifetime in seconds
     * @param string $key key to set
     * @param string $value value to set
     * @param int $lifetime lifetime in seconds
     * @return \React\Promise\Promise
     */
    public function set($key, $value, $lifetime)
    {
        $command = new \React\Promise\Deferred();
        $this->getConnection()->then(function() use($key, $value, $lifetime, $command){
            /** @noinspection PhpUndefinedMethodInspection */
            $this->client->setex($key, $lifetime, $value, function($status) use($command){
                switch(true){
                    case ($status instanceof \Predis\Response\Error):
                        $command->reject($status->getMessage());
                        break;
                    case ($status instanceof \Predis\Response\Status):
                        if($status->getPayload() == 'OK'){
                            $command->resolve();
                        }
                        else{
                            $command->reject('Got unknown response status from redis: ' . $status->getPayload());
                        }
                        break;
                    default:
                        $command->reject('Got unknown status from redis');
                        break;
                }
            });
        }, function() use($command){
            $command->reject('Cannot execute set command, no connection to server');
        });
        return $command->promise();
    }

    /**
     * Get key value from Redis
     * @param string $key
     * @return \React\Promise\Promise
     */
    public function get($key)
    {
        $command = new \React\Promise\Deferred();
        $this->getConnection()->then(function() use($command, $key){
            /** @noinspection PhpUndefinedMethodInspection */
            $this->client->get($key, function($response) use($command){
                if(is_null($response) || is_string($response)){
                    $command->resolve($response);
                }
                else{
                    $command->reject('Cannot execute get command, got unexpected response');
                }
            });
        }, function() use($command){
            $command->reject('Cannot execute get command, no connection to server');
        });
        return $command->promise();
    }
}
