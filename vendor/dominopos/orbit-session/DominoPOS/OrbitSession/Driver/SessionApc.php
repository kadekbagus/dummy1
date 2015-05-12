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

class SessionApc implements GenericInterface
{
    /**
     * Config
     * @var \DominoPOS\OrbitSession\SessionConfig
     */
    protected $config;

    /**
     * Constructor
     * @param \DominoPOS\OrbitSession\SessionConfig $config
     * @throws Exception
     */
    public function __construct($config)
    {
        if (!extension_loaded('apc'))
        {
            throw new Exception("APC Extensions not loaded", Session::ERR_UNKNOWN);
        }

        $this->config = $config;
    }

    /**
     * Start a session
     *
     * @param \DominoPOS\OrbitSession\SessionData $sessionData
     */
    public function start($sessionData)
    {
        Helper::touch($sessionData, $this->getConfig('expire'));

        $this->__writeSession($sessionData);
    }

    /**
     * Update a session
     *
     * @param DominoPOS\OrbitSession\SessionData
     */
    public function update($sessionData)
    {
        $this->__writeSession($sessionData);

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
        $current->value = [];

        $this->__writeSession($current);
    }

    /**
     * Get a session
     * @param string $sessionId
     * @return \DominoPOS\OrbitSession\SessionData
     */
    public function get($sessionId)
    {
        return $this->__getSession($sessionId);
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
        $current->value[$key] = $value;

        $this->__writeSession($current);

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

        $this->__writeSession($current);

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
     * set apc session
     *
     * @param \DominoPOS\OrbitSession\SessionData $sessionData
     */
    protected function __writeSession($sessionData)
    {
        $key   = $this->__apcKey($sessionData->id);
        $value = serialize($sessionData);

        apc_add($key, $value, $this->getConfig('expire'));
    }



    /**
     * Session ID to APC Key
     * @param string $sessionId
     * @return string
     */
    protected function __apcKey($sessionId)
    {
        $path = $this->getConfig('path', 'SESSIONS');
        return "{$path}/{$sessionId}";
    }



    /**
     * get apc session
     *
     * @param string $sessionId
     * @return \DominoPOS\OrbitSession\SessionData
     * @throws Exception
     */
    protected function __getSession($sessionId)
    {
        $key = $this->__apcKey($sessionId);

        $value = apc_fetch($key);

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
        $key = $this->__apcKey($sessionId);

        apc_delete($key);
    }
}