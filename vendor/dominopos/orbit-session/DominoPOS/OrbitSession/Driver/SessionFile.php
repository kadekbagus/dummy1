<?php namespace DominoPOS\OrbitSession\Driver;
/**
 * Session driver using File based interface.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class SessionFile implements GenericInterface
{
    /**
     * Config object
     */
    protected $config = NULL;

    /**
     * Constructor
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Start the session
     */
    public function start($sessionData)
    {
        $path = $this->config->getConfig('path');
        $fname = $path . DIRECTORY_SEPARATOR . $sessionData->id;
        $serialized = serialize($sessionData);

        file_put_contents($fname, $serialized);

        return array('session_data' => $sessionData, 'path' => $fname);
    }
}
