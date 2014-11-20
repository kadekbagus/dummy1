<?php
/**
 * Base controller class for Intermediate Controller
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;

class IntermediateBaseController extends Controller
{
    /**
     * Array of custome headers which sent to client
     *
     * @var array
     */
    protected $customHeaders = array();

    /**
     * Content type of the returned response
     *
     * @var string
     */
    protected $contentType = 'application/json';

    /**
     * Class constructor
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    public function __construct()
    {
        // CSRF protection
        $csrfProtection = Config::get('orbit.security.csrf.protect');

        if (Request::isMethod('post') && $csrfProtection === TRUE) {
            $csrfMode = Config::get('orbit.security.csrf.mode');

            try
            {
                $token = '';
                switch ($csrfMode) {
                    case 'angularjs':
                        $csrfTokenName = Config::get('orbit.security.csrf.angularjs.header_name_php');

                        // AngularJS send their token via HTTP Header
                        $token = $_SERVER[$csrfTokenName];

                        break;

                    case 'normal':
                    default:
                        $csrfTokenName = Config::get('orbit.security.csrf.normal.name');
                        $token = OrbitInput::post($csrfTokenName);
                }

                if (Session::token() !== $token) {
                    $message = Lang::get('validation.orbit.access.tokenmissmatch');
                    ACL::throwAccessForbidden($message);
                }
            } catch (ACLForbiddenException $e) {
                $response = new ResponseProvider();
                $response->code = $e->getCode();
                $response->status = 'error';
                $response->message = $e->getMessage();

                return $this->render($response);
            } catch (Exception $e) {
                $response = new ResponseProvider();
                $response->code = $e->getCode();
                $response->status = 'error';
                $response->message = $e->getMessage();

                return $this->render($response);
            }
        }
    }

    /**
     * Static method to instantiate the class
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return IntermediateBaseController
     */
    public static function create()
    {
        return new static;
    }

    /**
     * Render the output
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param ResponseProvider $response
     * @return Response
     */
    public function render($response=NULL)
    {
        $output = '';

        if (is_null($response)) {
            $response = new ResponseProvider();
        }

        switch ($this->contentType) {
            case 'application/json':
            default:
                $json = new \stdClass();
                $json->code = $response->code;
                $json->status = $response->status;
                $json->message = $response->message;
                $json->data = $response->data;

                $output = json_encode($json);

                // Allow Cross-Domain Request
                // http://enable-cors.org/index.html
                $this->customHeaders['Access-Control-Allow-Origin'] = '*';
                $this->customHeaders['Access-Control-Allow-Methods'] = 'GET, POST';

                $angularTokenName = Config::get('orbit.security.csrf.angularjs.header_name');
                $allowHeaders = array(
                    'Origin',
                    'Content-Type',
                    'Accept',
                    'Authorization',
                    'X-Request-With',
                    'X-Orbit-Signature',
                    $angularTokenName

                );
                $this->customHeaders['Access-Control-Allow-Headers'] = implode(',', $allowHeaders);
        }

        $headers = array('Content-Type' => $this->contentType) + $this->customHeaders;

        return Response::make($output, 200, $headers);
    }

    /**
     * Call the real controller API to handle the request.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return mixed
     */
    public function callApi()
    {
        // Get the API key of current user
        if (Auth::check()) {
            $user = Auth::user();

            // This will query the database if the apikey has not been set up yet
            $apikey = $user->apikey;

            // Generate the signature
            $_GET['apikey'] = $apikey->api_key;
            $_GET['apitimestamp'] = time();
            $signature = Generator::genSignature($apikey->api_secret_key);
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = $signature;
        }

        // Call the API class
        // Using either callApi('controller', 'method') or callApi('controller@method')
        $args = func_get_args();

        if (count($args) === 2) {
            $class = $args[0];
            $method = $args[1];
        } elseif (count($args) === 1) {
            list($class, $method) = explode('@', $args[0]);
        } else {
            $class = 'Foo';
            $method = 'Bar';
        }

        return $class::create()->$method();
    }

    /**
     * Get the name of API Controller which we want to call.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    protected function getTargetAPIController($method)
    {
        // Remove the 'Intermediate' string
        list($controller, $method) = explode('_', $method);

        // Append the controller with 'APIController', e.g:
        // 'Merchant' would be 'MerchantAPIController'
        $controller .= 'APIController';

        return array(
            'controller' => $controller,
            'method'     => $method
        );
    }

    /**
     * Magic method to call the API based on called method.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $method Method name
     * @param array $args Arguments
     * @return mixed
     */
    public function __call($method, $args)
    {
        $api = $this->getTargetAPIController($method);

        return $this->callApi($api['controller'], $api['method']);
    }
}
