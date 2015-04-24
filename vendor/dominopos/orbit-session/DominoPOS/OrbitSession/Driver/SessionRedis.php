<?php namespace DominoPOS\OrbitSession\Driver;
/**
 * Session driver using database as backend
 *
 * @author Yudi Rahono <yudi.rahono@dominopos.com>
 *
 */
use Exception;
use DominoPOS\OrbitSession\Helper;
use DominoPOS\OrbitSession\Session;

class SessionRedis implements GenericInterface
{
    /**
     * Redis instance
     *
     * @var \Redis
     */
    protected  $redis;

    /**
     * Config
     * @var \DominoPOS\OrbitSession\SessionConfig
     */
    protected $config;

    /**
     * Dirty Sessions Object
     *
     * @var array
     */
    protected $dirty;

    /**
     * Constructor
     * @param \DominoPOS\OrbitSession\SessionConfig $config
     * @throws Exception
     */
    public function __construct($config)
    {
        if (!extension_loaded('redis'))
        {
            throw new Exception("Redis Extensions not loaded", Session::ERR_UNKNOWN);
        }

        $this->redis  = new \Redis();
        $this->dirty  = [];
        $this->config = $config;
        $this->createConnection($config->getConfig('connection'));
    }

    /**
     * save outstanding cached session to redis
     */
    public function __destruct()
    {
        foreach ($this->dirty as $id=>$dirty) {
            $value = $dirty->value;
            if (array_key_exists('__dirty', $value))
            {
                unset($dirty->value['__dirty']);
                $this->__setSession($dirty);
                continue;
            }
        }
    }

    /**
     * Start a session
     *
     * @param \DominoPOS\OrbitSession\SessionData $sessionData
     */
    public function start($sessionData)
    {
        Helper::touch($sessionData, $this->getConfig('expire'));

        $this->__setSession($sessionData);
    }

    /**
     * Update a session
     *
     * @param DominoPOS\OrbitSession\SessionData
     */
    public function update($sessionData)
    {
        $this->__setSession($sessionData);

        return $sessionData;
    }

    /**
     * Destroy a session
     * @param string $sessionId
     */
    public function destroy($sessionId)
    {
        $this->__deleteSession($sessionId);
    }

    /**
     * Clear a session
     * @param string $sessionId
     */
    public function clear($sessionId)
    {
        $current        = $this->get($sessionId);
        $current->value = ['__dirty' => true];
        $this->dirty[$sessionId] = $current;
    }

    /**
     * Get a session
     * @param string $sessionId
     * @return \DominoPOS\OrbitSession\SessionData
     */
    public function get($sessionId)
    {
        return $this->getCurrent($sessionId);
    }

    /**
     * Write a value to a session.
     * @param string $sessionId
     * @param string $key
     * @param mixed $value
     * @return \DominoPOS\OrbitSession\SessionData
     */
    public function write($sessionId, $key, $value)
    {
        $current    = $this->get($sessionId);
        $current->value[$key]       = $value;
        $current->value['___dirty'] = true;

        $this->dirty[$sessionId] = $current;

        return $current;
    }

    /**
     * Read a value from a session
     * @param string $sessionId
     * @param string $key
     * @return mixed
     */
    public function read($sessionId, $key = null)
    {
        $current = $this->get($sessionId);

        return Helper::array_get($current->value, $key, null);
    }

    /**
     * Remove a value from a session
     * @param string $sessionId
     * @param string $key
     * @return \DominoPOS\OrbitSession\SessionData
     */
    public function remove($sessionId, $key)
    {
        $current = $this->get($sessionId);

        $current->value = Helper::array_remove($current->value, $key);
        $current->value['___dirty'] = true;

        $this->dirty[$sessionId] = $current;

        return $current;
    }

    /**
     * Delete expire session
     */
    public function deleteExpires()
    {
        // Nothing Use Expires Key
    }

    /**
     * Initial Redis Connections
     *
     * @param array $connection
     */
    protected function createConnection($connection)
    {
        $host     = Helper::array_get($connection, 'host', 'localhost');
        $port     = Helper::array_get($connection, 'port', 6379);
        $database = Helper::array_get($connection, 'database', 0);

        $this->redis->connect($host, $port);
        $this->redis->select($database);
    }

    /**
     * Get Current session from cache or database
     *
     * @param string $sessionId
     * @return mixed
     */
    protected function getCurrent($sessionId)
    {
        if (array_key_exists($sessionId, $this->dirty))
        {
            $current = $this->dirty[$sessionId];
        } else {
            $current = $this->__getSession($sessionId);
            $this->dirty[$sessionId] = $current;
        }

        return $current;
    }


    /**
     * Config Proxy method
     *
     * @param $name
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig($name, $default = null)
    {
        return $this->config->getConfig($name, $default);
    }

    /**
     * set redis session
     *
     * @param \DominoPOS\OrbitSession\SessionData $sessionData
     */
    protected function __setSession($sessionData)
    {
        $key   = $this->__redisKey($sessionData->id);
        $value = serialize($sessionData);

        $this->redis->set($key, $value);
        $this->redis->expireAt($key, $sessionData->expireAt);
    }



    /**
     * Session ID to Redis Key
     * @param string $sessionId
     * @return string
     */
    protected function __redisKey($sessionId)
    {
        $path = $this->getConfig('path', 'SESSIONS');
        return "{$path}:{$sessionId}";
    }



    /**
     * get redis session
     *
     * @param string $sessionId
     * @return \DominoPOS\OrbitSession\SessionData
     * @throws Exception
     */
    protected function __getSession($sessionId)
    {
        $key = $this->__redisKey($sessionId);

        $value = $this->redis->get($key);

        if (FALSE === ($value = unserialize($value)))
        {
            throw new Exception('Could not unserialize the session data.', Session::ERR_SESS_NOT_FOUND);
        }

        return $value;
    }


    /**
     * delete session on storage
     *
     * @param string $sessionId
     */
    protected function __deleteSession($sessionId)
    {
        $key = $this->__redisKey($sessionId);
        $this->redis->del($key);
    }
}