<?php namespace OrbitShop\API\v1;
/**
 * Base API Controller.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;

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
     * Contructor
     *
     * @param string $contentType - HTTP content type that would be sent to client
     */
    public function __construct($contentType = 'application/json') {
        // default content type set to JSON
        $this->contentType = $contentType;

        // Set the default response
        $this->response = new ResponseProvider();
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
    protected function checkAuth()
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
    protected function render($httpCode=200)
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
        }

        return Response::make($output, $httpCode, array('Content-Type' => $this->contentType));
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
}
