<?php namespace DominoPOS\OrbitSession\Driver;
/**
 * Session driver using database as backend
 *
 * @author Yudi Rahono <yudi.rahono@dominopos.com>
 *
 */

use Exception;
use DominoPOS\OrbitSession\Session;
use PDO;

class SessionDatabase implements GenericInterface
{
    /**
     * config object
     *
     * @var DominoPOS\OrbitSession\SessionConfig
     */
    protected $config = NULL;

    /**
     * pdo connection
     *
     * @var PDO
     */
    protected $pdo;


    /**
     * dirty session object
     *
     * @var array
     */
    protected $dirty;

    /**
     * Constructor
     *
     * @param DominoPOS\OrbitSession\SessionConfig
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->dirty  = [];
        $this->pdo = $config->getConfig('pdo');
    }

    /**
     * Destructor
     *
     */
    public function __destruct()
    {
        foreach ($this->dirty as $id=>$dirty) {
            $value = $dirty->value;
            if (array_key_exists('__dirty', $value))
            {
                unset($dirty->value['__dirty']);
                $this->__updateSession($id, $dirty);
                continue;
            }

            if (array_key_exists('__delete', $value))
            {
                $this->__deleteSession($id);
                continue;
            }
        }
    }

    /**
     * Start a session
     *
     *
     * @param DominoPOS\OrbitSession\SessionData
     * @return SessionDatabase
     */
    public function start($sessionData)
    {
        $this->__insertSession($sessionData);

        return $this;
    }

    /**
     * Update a session
     *
     * @param DominoPOS\OrbitSession\SessionData
     * @return SessionDatabase
     */
    public function update($sessionData)
    {
        return $this->__updateSession($sessionData->id, $sessionData);
    }

    /**
     * Destroy a session
     * @param string $sessionId
     */
    public function destroy($sessionId)
    {
        $this->dirty[$sessionId]->value = ['___delete' => true];
    }

    /**
     * Clear a session
     * @param string $sessionId
     */
    public function clear($sessionId)
    {
        $this->dirty[$sessionId]->value = ['___dirty' => true];
    }

    /**
     * Get a session
     * @param string $sessionId
     * @return array
     */
    public function get($sessionId)
    {
        return $this->getCurrent($sessionId);
    }

    /**
     * Write a value to a session.
     * @param integer $sessionId
     * @param mixed $key
     * @param mixed $value
     * @return array
     */
    public function write($sessionId, $key, $value)
    {
        $current             = $this->getCurrent($sessionId);
        $current->value[$key]       = $value;
        $current->value['___dirty'] = true;

        $this->dirty[$sessionId] = $current;

        return $current;
    }

    /**
     * Read a value from a session
     * @param string $sessionId
     * @param mixed $key
     * @return mixed
     */
    public function read($sessionId, $key)
    {
        $current = $this->getCurrent($sessionId);

        return $current->value[$key];
    }

    /**
     * Remove a value from a session
     * @param $sessionId
     * @param $key
     * @return array
     */
    public function remove($sessionId, $key)
    {
        $current = $this->getCurrent($sessionId);
        unset($current->value[$key]);
        $current->value['___dirty'] = true;

        $this->dirty[$sessionId] = $current;

        return $current;
    }

    /**
     * Delete expire session
     */
    public function deleteExpires()
    {
        $this->__deleteSession(NULL, true);
    }

    /**
     * @param string $sessionId
     * @return mixed
     */
    private function getCurrent($sessionId)
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
     * @param string $sessionId
     * @return array
     * @throws Exception
     */
    private function __getSession($sessionId)
    {
        $query = $this->pdo->query("
            SELECT * FROM `{$this->getConfig('table')}`
             WHERE session_id = {$this->pdo->quote($sessionId)}
        ");

        $result = $query->fetchObject();

        if (FALSE === ($result = unserialize($result->session_data)))
        {
            throw new Exception('Could not unserialize the session file.', Session::ERR_SESS_NOT_FOUND);
        }

        return $result;
    }

    private function __updateSession($sessionId, $sessionData)
    {
        $data         = $this->pdo->quote(serialize($sessionData));
        $id           = $this->pdo->quote($sessionId);
        $expireAt     = $this->pdo->quote($sessionData->expireAt);
        $lastActivity = $this->pdo->quote($sessionData->lastActivityAt);

        $query = $this->pdo->prepare("
            UPDATE {$this->getConfig('table')}
            SET
              session_data   = {$data},
              last_activity  = {$lastActivity},
              expire_at      = {$expireAt}
            WHERE session_id = {$id}
        ");

        return $query->execute();
    }

    private function __deleteSession($sessionId, $clean = false)
    {

        $id = $this->pdo->quote($sessionId);
        $cleanStatement = '';

        if ($clean)
        {
            $cleanStatement = "OR expire_at < {time()}";
        }

        $prepared = $this->pdo->prepare("
            DELETE FROM `{$this->getConfig('table')}`
            WHERE session_id = {$id} {$cleanStatement}
        ");

        $this->pdo->exec($prepared);
    }



    private function __insertSession($sessionData)
    {

        $this->touch($sessionData);
        $data         = $this->pdo->quote(serialize($sessionData));
        $id           = $this->pdo->quote($sessionData->id);
        $expireAt     = $this->pdo->quote($sessionData->expireAt);
        $lastActivity = $this->pdo->quote($sessionData->lastActivityAt);

        $query =$this->pdo->prepare("
            INSERT INTO `{$this->getConfig('table')}` (session_id, session_data, expire_at, last_activity)
            VALUES ({$id}, {$data}, {$expireAt}, {$lastActivity})
        ");

        $query->execute();
    }

    private function getConfig($name)
    {
        return $this->config->getConfig($name);
    }

    /**
     * Touch the session data to change the expiration time and last activity.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param sessionData &$sessionData
     * @return void
     */
    protected function touch(&$sessionData)
    {
        // Refresh the session
        $expire = $this->config->getConfig('expire');
        if ($expire !== 0) {
            $sessionData->expireAt = time() + $expire;
        }

        // Last activity
        $sessionData->lastActivityAt = time();
    }
}