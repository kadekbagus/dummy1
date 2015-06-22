<?php namespace Orbit;
/**
 * Helper to get config for default per page listing and max record returned
 * for a set list of response.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Config;

class Pagination
{
    /**
     * The default per page in pagination list.
     *
     * @var int
     */
    protected $perPage = 20;

    /**
     * The default max record in a list.
     *
     * @var int
     */
    protected $maxRecord = 50;

    /**
     * List of config for pagination or max record.
     *
     * @var array
     */
    protected $configList = [
        'per_page'  => [
            'default'   => 'orbit.pagination.%s.per_page',
            'fallback'  => 'orbit.pagination.per_page'
        ],

        'max_record'    => [
            'default'   => 'orbit.pagination.%s.max_record',
            'fallback'  => 'orbit.pagination.max_record'
        ]
    ];

    /**
     * Constructor
     *
     * @param array $config (optional)
     * @return void
     */
    public function __construct(array $config=array())
    {
        $this->configList = $config + $this->configList;
    }

    /**
     * Static method to instantiate the class.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Setting
     */
    public static function create(array $config=array())
    {
        return new static($config);
    }

    /**
     * Get The value of default per page pagination setting for particular
     * list.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $listname
     * @return Pagination
     */
    public function setPerPage($listname)
    {
        $defaultConfig = sprintf($this->configList['per_page']['default'], $listname);

        // Get default per page (take)
        $perPage = (int)Config::get($defaultConfig);
        if ($perPage <= 0) {

            // Fallback
            $fallbackConfig = sprintf($this->configList['per_page']['fallback'], $listname);

            $perPage = (int)Config::get($fallbackConfig);
            if ($perPage <= 0) {
                // Second fallback
                // Default would be taken from the object attribute $perPage
                return $this;
            }
        }

        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Get The value of default per page pagination setting for particular
     * list.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $listname
     * @return Pagination
     */
    public function setMaxRecord($listname)
    {
        $defaultConfig = sprintf($this->configList['max_record']['default'], $listname);

        // Get default per page (take)
        $maxRecord = (int)Config::get($defaultConfig);
        if ($maxRecord <= 0) {

            // Fallback
            $fallbackConfig = sprintf($this->configList['max_record']['fallback'], $listname);

            $maxRecord = (int)Config::get($fallbackConfig);
            if ($maxRecord <= 0) {
                // Second fallback
                // Default would be taken from the object attribute $perPage
                return $this;
            }
        }

        $this->maxRecord = $maxRecord;

        return $this;
    }

    /**
     * Magic method to get the property value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return mixed
     */
    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }
}
