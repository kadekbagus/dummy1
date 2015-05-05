<?php
/**
 * PHP Unit Test for EmployeeAPIController#getSearchEmployee
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */

use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class getSearchEmployeeTest extends TestCase
{
    protected $useTruncate = false;
    protected $authData;
    protected $employees;
    private $baseUrl  = '/api/v1/employee/list';

    public function setUp()
    {
        parent::setUp();

        $this->authData   = Factory::create('Apikey', ['user_id' => 'factory:user_super_admin']);
        $this->employees  = Factory::times(10)->create('Employee');
    }

    public function testOK_get_search_employee_without_additional_parameter()
    {
        $makeRequest = function ($getData) {
            $_GET                 = array_merge($_GET, $getData);
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['REMOTE_ADDR']            = '127.0.0.1';
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = $makeRequest([]);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(0, $response->data->total_records);
    }


    public function testOK_get_search_employee_with_employee_id_char_like()
    {
        $user = Factory::create('User', ['username' => 'employee.101']);
        Factory::create('Employee', ['user_id' => $user->user_id]);
        $makeRequest = function ($getData) {
            $_GET                 = array_merge($_GET, $getData);
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['REMOTE_ADDR']            = '127.0.0.1';
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = $makeRequest(['username' => ['employee.101']]);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(0, $response->data->total_records);
    }
}