<?php

use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use DominoPOS\OrbitSession\SessionData;
use Laracasts\TestDummy\Factory;

class TestCase extends Illuminate\Foundation\Testing\TestCase {

	protected static $registered;

	protected static $directories;

	protected static $cachedApplication;

	protected static $dataBag = [];

	protected $useTruncate = true;

    protected $useIntermediate = false;
    /**
     * @var \DominoPOS\OrbitSession\Session
     */
    protected $session;
    /**
     * @var \ApiKey
     */
    protected $authData;
    protected $eventNameSpace;

    /**
	 * Creates the application.
	 *
	 * @return \Symfony\Component\HttpKernel\HttpKernelInterface
	 */
	public function createApplication()
	{
		$unitTesting = true;

		$testEnvironment = 'testing';

		if (static::$cachedApplication)
		{
			return static::$cachedApplication;
		}

		return require __DIR__.'/../../bootstrap/start.php';
	}

    public function setUp()
    {
        parent::setUp();

		if (empty(static::$dataBag)) {
			DB::beginTransaction();
			static::prepareDatabase();
			DB::commit();
		}

		if (! $this->useTruncate) {
			DB::beginTransaction();
		}


		foreach (static::$dataBag as $name=>$data)
		{
			$this->$name = $data;
		}

        if ($this->useIntermediate)
        {
            $_SERVER['HTTP_USER_AGENT'] = 'test\browser 1.0';
            $_SERVER['REMOTE_ADDR']     = '127.0.0.1';

            $sessionConfig  = new SessionConfig(Config::get('orbit.session'));
            if (empty($this->authData))
            {
                $this->authData = Factory::create('apikey_super_admin');
            }
            $sessionData    = new SessionData([
                'logged_in' => TRUE,
                'user_id'   => $this->authData->user_id,
            ]);
            $this->session  = new Session($sessionConfig);
            $this->session->rawUpdate($sessionData);
            $this->session->setSessionId($sessionData->id);
        }
    }

	public function tearDown()
	{
        // Truncate all tables, except migrations
        if(! $this->useTruncate)  {
			DB::rollback();
		}

		unset($_GET);
		unset($_POST);
		$_GET = array();
		$_POST = array();

		unset($_SERVER['HTTP_X_ORBIT_SIGNATURE'],
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['REQUEST_URI']
		);

		// Clear every event dispatcher so we get no queue event on each
		// test
		$events = array(
			"orbit.{$this->eventNameSpace}.before.auth",
			"orbit.{$this->eventNameSpace}.after.auth",
			"orbit.{$this->eventNameSpace}.before.authz",
			"orbit.{$this->eventNameSpace}.authz.notallowed",
			"orbit.{$this->eventNameSpace}.after.authz",
			"orbit.{$this->eventNameSpace}.before.validation",
			"orbit.{$this->eventNameSpace}.after.validation",
			"orbit.{$this->eventNameSpace}.before.save",
			"orbit.{$this->eventNameSpace}.after.save",
			"orbit.{$this->eventNameSpace}.after.commit",
			"orbit.{$this->eventNameSpace}.access.forbidden",
			"orbit.{$this->eventNameSpace}.invalid.arguments",
			"orbit.{$this->eventNameSpace}.general.exception",
			"orbit.{$this->eventNameSpace}.before.render"
		);
		foreach ($events as $event) {
			Event::forget($event);
		}
	}

	public static function setupBeforeClass()
	{
		$vendor = __DIR__ . '/../../vendor/';

		static::$directories = array(
			$vendor . 'fzaninotto/faker/src',
			$vendor . 'laracasts/testdummy/src',
			$vendor . 'hamcrest/hamcrest-php/hamcrest',
			$vendor . 'mockery/mockery/library'
		);

		if (! static::$registered) {
			static::$registered = spl_autoload_register(array('TestCase', 'loadTestLibrary'));
		}

		if (! static::$cachedApplication)
		{
			static::$cachedApplication = require __DIR__.'/../../bootstrap/start.php';
		}

		Factory::$factoriesPath = __DIR__ . '/factories';
	}

	public static function tearDownAfterClass()
	{
		static::$dataBag = [];

		$tables = DB::select('SHOW TABLES');
		$prefix = DB::getTablePrefix();
		$statement = "";
		$tables_in_database = 'Tables_in_' . DB::getDatabaseName();
		foreach ($tables as $table) {
			if ($table->$tables_in_database !== "{$prefix}migrations") {
				$statement .= "
				TRUNCATE TABLE {$table->$tables_in_database};";
			}
		}

		DB::unprepared($statement);
	}

	public static function prepareDatabase()
	{
		// Override this;
	}

	protected static function addData($name, $data)
	{
		static::$dataBag[$name] = $data;
	}

	public static function loadTestLibrary($className)
	{
		$className = ltrim($className, '\\');
		$fileName  = '';
		$namespace = '';

	    if ($lastNsPos = strripos($className, '\\')) {
	        $namespace = substr($className, 0, $lastNsPos);
	        $className = substr($className, $lastNsPos + 1);
	        $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
	    }

		foreach (static::$directories as $directory) {
			if (file_exists($path = $directory . DIRECTORY_SEPARATOR . $className . '.php')) {
				require_once $path;

				return true;
			}

			if (file_exists($path = $directory . DIRECTORY_SEPARATOR . $fileName . $className . '.php')) {
				require_once $path;

				return true;
			}
		}

	    return false;
	}

}

/**
 * Filter an array using keys instead of values.
 *
 * @param  array    $array
 * @param  callable $callback
 * @return array
 */
function filter_array_keys(array $array, $callback)
{
	$matchedKeys = array_filter(array_keys($array), $callback);

	return array_intersect_key($array, array_flip($matchedKeys));
}
