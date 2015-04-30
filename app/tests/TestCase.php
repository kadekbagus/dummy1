<?php

use Laracasts\TestDummy\Factory;

class TestCase extends Illuminate\Foundation\Testing\TestCase {

	protected static $registered;

	protected static $directories;

	protected static $dataBag = [];

	protected $useTruncate = true;

	/**
	 * Creates the application.
	 *
	 * @return \Symfony\Component\HttpKernel\HttpKernelInterface
	 */
	public function createApplication()
	{
		$unitTesting = true;

		$testEnvironment = 'testing';

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
			'orbit.retailer.postnewretailer.before.auth',
			'orbit.retailer.postnewretailer.after.auth',
			'orbit.retailer.postnewretailer.before.authz',
			'orbit.retailer.postnewretailer.authz.notallowed',
			'orbit.retailer.postnewretailer.after.authz',
			'orbit.retailer.postnewretailer.before.validation',
			'orbit.retailer.postnewretailer.after.validation',
			'orbit.retailer.postnewretailer.before.save',
			'orbit.retailer.postnewretailer.after.save',
			'orbit.retailer.postnewretailer.after.commit',
			'orbit.retailer.postnewretailer.access.forbidden',
			'orbit.retailer.postnewretailer.invalid.arguments',
			'orbit.retailer.postnewretailer.general.exception',
			'orbit.retailer.postnewretailer.before.render'
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
			$vendor . 'laracasts/testdummy/src'
		);

		if (! static::$registered) {
			static::$registered = spl_autoload_register(array('TestCase', 'loadTestLibrary'));
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
				$statement = "{$statement}
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
