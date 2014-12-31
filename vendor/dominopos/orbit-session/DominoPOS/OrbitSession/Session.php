<?php namespace DominoPOS\OrbitSession;
/**
 * Simple session class for orbit.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Exception;

class Session
{
    /**
     * Hold the SessionConfig object
     *
     * @var SessionConfig
     */
    protected $config = NULL;

    /**
     * The session driver interface
     *
     * @var GenericInterface
     */
    protected $driver = NULL;

    /**
     * The current ID of the session
     *
     * @var string
     */
    protected $sessionId = NULL;

    /**
     * List of static error codes
     */
    const ERR_UNKNOWN = 51;
    const ERR_IP_MISS_MATCH = 52;
    const ERR_UA_MISS_MATCH = 53;
    const ERR_SESS_NOT_FOUND = 54;
    const ERR_SESS_EXPIRE = 55;
    const ERR_SAVE_ERROR = 56;
    const ERR_READ_ERROR = 57;
    const ERR_DELETE_ERROR = 58;

    /**
     * Constructor
     */
    public function __construct($config)
    {
        $this->config = $config;

        // Example: if the driver 'file' then the driver name would be 'SessionFile'
        $driverName = 'DominoPOS\\OrbitSession\\Driver\\Session' . ucwords($config->getConfig('driver'));
        $this->driver = new $driverName($config);
    }

    /**
     * Start the session
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param mixed $data - Data which will be stored on session
     * @return SessionData
     */
    public function start(array $data)
    {
        // Check if we got session id
        $availabilities = $this->config->getConfig('availability');
        foreach ($availabilities as $availability) {
            $source = $this->config->getConfig('session_origin.' . $availability . '.name');

            if ($availability === 'header') {
                // Turn X-Something to 'HTTP_X_SOMETHING'
                $source = strtoupper($source);
                $source = 'HTTP_' . str_replace('-', '_', $source);

                if (isset($_SERVER[$source]) && ! empty($_SERVER[$source])) {
                    $this->sessionId = $_SERVER[$source];

                    // Found it, no need further check
                    break;
                }
            }

            if ($availability === 'query_string') {
                if (isset($_GET[$source]) && ! empty($_GET[$source])) {
                    $this->sessionId = $_GET[$source];

                    // Found it, no need further check
                    break;
                }
            }

            if ($availability === 'cookie') {
                if (isset($_COOKIE[$source])) {
                    $this->sessionId = $_COOKIE[$source];
                }
            }
        }

        if (empty($this->sessionId))
        {
            $sessionData = new SessionData($data);
            $sessionData->createdAt = time();
            $this->driver->start($sessionData);

            $this->sessionId = $sessionData->id;
        } else {
            try {
                $sessionData = $this->driver->get($this->sessionId);

                // We got the session, check if we use strict checking
                if ($this->config->getConfig('strict') === TRUE) {
                    if ($sessionData->userAgent !== $_SERVER['HTTP_USER_AGENT']) {
                        throw new Exception ('User agent miss match.', static::ERR_UA_MISS_MATCH);
                    }

                    if ($sessionData->ipAddress !== $_SERVER['REMOTE_ADDR']) {
                        throw new Exception ('IP address miss match.', static::ERR_IP_MISS_MATCH);
                    }
                }

                $this->driver->update($sessionData);
            } catch (Exception $e) {
                switch ($e->getCode()) {
                    // User agent or IP miss match, sesion hijacking?
                    case static::ERR_IP_MISS_MATCH:
                    case static::ERR_UA_MISS_MATCH:
                        throw new Exception($e->getMessage(), $e->getCode());
                        break;

                    // The session file probably does not exists, so create new session
                    default:
                        $sessionData = new SessionData($data=array());
                        $sessionData->createdAt = time();
                        $this->driver->start($sessionData);

                        $this->sessionId = $sessionData->id;
                }
            }
        }

        return $this;
    }

    /**
     * Update the session
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $data
     * @return Session
     */
    public function update(array $data)
    {
        if (! $this->sessionId) {
            $this->driver->start($data);
        } else {
            $sessionData = $this->driver->get($this->sessionId);
            $sessionData->value = $data;
            $this->driver->update($sessionData);
        }

        return $this;
    }

    /**
     * Set a data on session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key
     * @param string $value
     * @return Session
     */
    public function write($key, $value)
    {
        $this->driver->write($this->sessionId, $key, $value);

        return $this;
    }

    /**
     * Read a data on session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key
     * @return mixed
     */
    public function read($key)
    {
        return $this->driver->read($this->sessionId, $key);
    }

    /**
     * Remove a data on session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key
     * @return mixed
     */
    public function remove($key)
    {
        return $this->driver->remove($this->sessionId, $key);
    }

    /**
     * Clear a data on session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return mixed
     */
    public function clear()
    {
        return $this->driver->clear($this->sessionId);
    }

    /**
     * Destroy a session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return boolean
     */
    public function destroy()
    {
        return $this->driver->destroy($this->sessionId);
    }

    /**
     * Get the session data object
     *
     * @author Rio Astamal <me@rioastamal.ne>
     * @param string id - Session Id
     * @return SessionData
     */
    public function getSession()
    {
        return $this->driver->get($this->sessionId);
    }
}
