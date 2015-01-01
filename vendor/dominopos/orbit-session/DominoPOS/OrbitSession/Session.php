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
     * @param string $mode - mode of start, valid: 'default', 'no-session-creation'
     * @return SessionData
     */
    public function start(array $data=array(), $mode='default')
    {
        // Check if we got session id
        $availabilities = $this->config->getConfig('availability');
        $now = time();

        // Check session from header first
        if (array_key_exists('header', $availabilities) && $availabilities['header'] === TRUE) {
            // Turn X-Something to 'HTTP_X_SOMETHING'
            $source = $this->config->getConfig('session_origin.header.name');
            $source = strtoupper($source);
            $source = 'HTTP_' . str_replace('-', '_', $source);

            if (isset($_SERVER[$source]) && ! empty($_SERVER[$source])) {
                $this->sessionId = $_SERVER[$source];
            }

        // Check session from query string
        } elseif (array_key_exists('query_string', $availabilities) && $availabilities['query_string'] === TRUE) {
            $source = $this->config->getConfig('session_origin.query_string.name');
            if (isset($_GET[$source]) && ! empty($_GET[$source])) {
                $this->sessionId = $_GET[$source];
            }

        // Check session from cookie
        } elseif (array_key_exists('cookie', $availabilities) && $availabilities['cookie'] === TRUE) {
            $source = $this->config->getConfig('session_origin.cookie.name');
            if (isset($_COOKIE[$source]) && ! empty($_COOKIE[$source])) {
                $this->sessionId = $_COOKIE[$source];
            }
        }

        if (empty($this->sessionId)) {
            if ($mode === 'no-session-creation') {
                throw new Exception ('No session found.', static::ERR_SESS_NOT_FOUND);
            }

            $sessionData = new SessionData($data);
            $sessionData->createdAt = $now;
            $this->driver->start($sessionData);

            $this->sessionId = $sessionData->id;

            // Send the session id via cookie to the client
            $this->sendCookie($sessionData->id);
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

                // Does the session already expire?
                $expireAt = $sessionData->expireAt;
                if ($expireAt < $now) {
                    throw new Exception ('Session has ben expires.', static::ERR_SESS_EXPIRE);
                }

                $this->driver->update($sessionData);
            } catch (Exception $e) {
                switch ($e->getCode()) {
                    // User agent or IP miss match, sesion hijacking?
                    case static::ERR_IP_MISS_MATCH:
                    case static::ERR_UA_MISS_MATCH:
                        throw new Exception($e->getMessage(), $e->getCode());
                        break;

                    // Clear the session value
                    case static::ERR_SESS_EXPIRE:
                        $this->driver->clear($this->sessionId);
                        throw new Exception($e->getMessage(), $e->getCode());
                        break;

                    // The session file probably does not exists, so create new session
                    default:
                        $sessionData = new SessionData($data=array());
                        $sessionData->createdAt = time();
                        $this->driver->start($sessionData);

                        $this->sessionId = $sessionData->id;

                        // Send the session id via cookie to the client
                        $this->sendCookie($sessionData->id);
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

    /**
     * Get the session id
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * Get session config
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return SessionConfig
     */
    public function getSessionConfig()
    {
        return $this->config;
    }

    /**
     * Send session id to the client via cookie.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    protected function sendCookie($sessionId)
    {
        $availabilities = $this->config->getConfig('availability');

        if (array_key_exists('cookie', $availabilities)) {
            $cookieConfig = $this->config->getConfig('session_origin.cookie');
            setcookie(
                $cookieConfig['name'],
                $sessionId,
                $cookieConfig['expire'] + time(),
                $cookieConfig['path'],
                $cookieConfig['domain'],
                $cookieConfig['secure'],
                $cookieConfig['httponly']
            );
        }
    }
}
