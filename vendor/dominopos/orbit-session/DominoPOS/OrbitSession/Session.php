<?php namespace DominoPOS\OrbitSession;
/**
 * Simple session class for orbit.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class Session
{
    /**
     * Hold the SessionConfig object
     *
     * @var SessionConfig
     */
    protected $config = NULL;

    /**
     * List of static error codes
     */
    const ERR_UNKNOWN = 51;
    const ERR_IP_MISS_MATCH = 52;
    const ERR_UA_MISS_MATCH = 53;
    const ERR_SESS_NOT_FOUND = 54;
    const ERR_SESS_EXPIRE = 55;
    const ERR_SAVE_ERROR = 56;

    /**
     * Constructor
     */
    public function __construct($config)
    {
        $this->config = $config;
    }
}
