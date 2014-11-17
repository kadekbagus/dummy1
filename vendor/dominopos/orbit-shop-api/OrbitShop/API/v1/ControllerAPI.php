<?php namespace OrbitShop\API\v1;
/**
 * Base API Controller.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use PDO;

abstract class ControllerAPI extends Controller
{
    /**
     * The return value (response) of Controller API.
     *
     * @var ResponseAPI
     */
    public $response = NULL;

    /**
     * Direct access to the PHP PDO object which currently hold the connection.
     *
     * @var PDO
     */
    public $pdo = NULL;

    /**
     * The HTTP response type.
     *
     * @var string
     */
    public $contentType = 'application/json';

    /**
     * Store the Authentication.
     *
     * @var OrbitShopAPI
     */
    public $api;

    /**
     * Flag whether to use authentication or not.
     *
     * @var requireAuth
     */
    public $requireAuth = TRUE;

    /**
     * Maximum number of record that should be returned from query.
     *
     * @var int
     */
    public $maxRecord = 100;

    /**
     * Default number of record that should be returned if no limit spesified.
     *
     * @var int
     */
    public $defaultNumberOfRecord = 20;

    /**
     * How long request should considered invalid in seconds.
     *
     * @var int
     */
    public $expiresTime = 60;

    /**
     * Custom headers sent to the client
     *
     * @var array
     */
    public $customHeaders = array();

    /**
     * Contructor
     *
     * @param string $contentType - HTTP content type that would be sent to client
     */
    public function __construct($contentType = 'application/json') {
        // default content type set to JSON
        $this->contentType = $contentType;

        // Set the default response
        $this->response = new ResponseProvider();

        // Assign the PDO object
        $this->pdo = DB::connection()->getPdo();

        $expires = Config::get('orbit.api.signature.expiration');
        if ((int)$expires > 0)
        {
            $this->expiresTime = $expires;
        }
    }

    /**
     * Static method to instantiate the object.
     *
     * @param string $contentType
     * @return ControllerAPI
     */
    public static function create($contentType = 'application/json')
    {
        return new static($contentType);
    }

    /**
     * Method to authenticate the API consumer.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param string $input_secret_name - Default name for getting secret key input.
     * @return void
     * @thrown Exception
     */
    public function checkAuth()
    {
        // Get the api key from query string
        $clientkey = (isset($_GET['apikey']) ? $_GET['apikey'] : '');

        // Instantiate the OrbitShopAPI
        $this->api = new OrbitShopAPI($clientkey);

        // Set the request expires time
        $this->api->expiresTimeFrame = $this->expiresTime;

        // Run the signature check routine
        $this->api->checkSignature();
    }

    /**
     * Return the output of the API to the caller.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $httpCode - The HTTP status code response.
     * @return OrbitShop\API\v1\ResponseProvider | string
     */
    public function render($httpCode=200)
    {
        $output = '';

        switch ($this->contentType) {
            case 'raw':
                return $this->response;
                break;

            case 'application/json':
            default:
                $json = new \stdClass();
                $json->code = $this->response->code;
                $json->status = $this->response->status;
                $json->message = $this->response->message;
                $json->data = $this->response->data;

                $output = json_encode($json);

                // Allow Cross-Domain Request
                // http://enable-cors.org/index.html
                $this->customHeaders['Access-Control-Allow-Origin'] = '*';
                $this->customHeaders['Access-Control-Allow-Methods'] = 'GET, POST';
                $this->customHeaders['Access-Control-Allow-Headers'] = 'Origin, Content-Type, Accept, Authorization, X-Request-With, X-Orbit-Signature';
        }

        $headers = array('Content-Type' => $this->contentType) + $this->customHeaders;

        return Response::make($output, $httpCode, $headers);
    }

    /**
     * Magic method which alled when calling undefined method handler on
     * controller.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $method - The method name
     * @param array $args - The arguments
     * @return OrbitShop\API\v1\ResponseProvider | string
     */
    public function __call($method, $args)
    {
        $this->response->code = 404;
        $this->response->status = 'error';
        $this->response->message = 'Request URL not found';
        $this->response->data = NULL;

        return $this->render(404);
    }

    /**
     * Begin the database transaction.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    public function beginTransaction()
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Rollback the transaction.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    public function rollBack()
    {
        // Make sure we are in transaction mode, to prevent the rollback()
        // complaining
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Commit the changes to database.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    public function commit()
    {
        $this->pdo->commit();
    }
}
