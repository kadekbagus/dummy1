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

class SessionMemcached implements GenericInterface
{
    /**
     * Memcached instance
     *
     * @var \Memcached
     */
    protected  $memcached;

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
        if (!extension_loaded('memcached'))
        {
            throw new Exception("Memcached Extensions not loaded", Session::ERR_UNKNOWN);
        }

        $this->memcached  = new \Memcached();
        $this->dirty  = [];
        $this->config = $config;
        $this->createConnection($config->getConfig('connection'));
    }

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
     * Initial Memcached Connections
     *
     * @param array $connection
     */
    protected function createConnection($connection)
    {
        if(! is_array($connection))
        {
            $connection = [$connection];
        }

        foreach($connection as $c)
        {
            $host     = Helper::array_get($c, 'host', 'localhost');
            $port     = Helper::array_get($c, 'port', 11211);

            $this->memcached->addServer($host, $port);
        }
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
     * set memcached session
     *
     * @param \DominoPOS\OrbitSession\SessionData $sessionData
     */
    protected function __setSession($sessionData)
    {
        $key   = $this->__memcachedKey($sessionData->id);
        $value = serialize($sessionData);

        $this->memcached->set($key, $value, $this->getConfig('expire'));
    }



    /**
     * Session ID to Memcached Key
     * @param string $sessionId
     * @return string
     */
    protected function __memcachedKey($sessionId)
    {
        $path = $this->getConfig('path', 'SESSIONS');
        return "{$path}-{$sessionId}";
    }



    /**
     * get memcached session
     *
     * @param string $sessionId
     * @return \DominoPOS\OrbitSession\SessionData
     * @throws Exception
     */
    protected function __getSession($sessionId)
    {
        $key = $this->__memcachedKey($sessionId);

        $value = $this->memcached->get($key);

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
        $key = $this->__memcachedKey($sessionId);
        $this->memcached->delete($key);
    }
}