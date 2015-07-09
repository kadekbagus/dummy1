<?php
/**
 * PHP Unit Test for EmployeeAPIController#postNewEmployee
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postUpdateEmployeeTest extends TestCase
{
    protected $authData;
    protected $retailer;
    protected $employee;
    protected $user;
    private $baseUrl  = '/api/v1/employee/update';

    public static function prepareDatabase()
    {
        $role       = Factory::create('role_admin');
        $permission = Factory::create('Permission', ['permission_name' => 'update_employee']);
        $user       = Factory::create('User', ['user_role_id' => $role->role_id]);
        $merchant   = Factory::create('Merchant', ['user_id' => $user->user_id]);

        static::addData('authData', Factory::create('Apikey', ['user_id' => $user->user_id]));
        static::addData('retailer', Factory::create('Retailer', ['parent_id' => $merchant->merchant_id]));
        static::addData('employee', Factory::create('Employee', ['user_id' => $user->user_id]));
        static::addData('merchant', $merchant);
        static::addData('user',     $user);

        Factory::create('PermissionRole', ['role_id' => $role->role_id, 'permission_id' => $permission->permission_id]);
        Factory::create('UserDetail', ['user_id' => $user->user_id]);
        Factory::create('Role', ['role_name' => 'cashier']);
    }

    public function testOK_post_update_employee_with_valid_parameters()
    {
        $makeRequest = function ($postData) {
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST = array_merge($_POST, $postData);

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['REMOTE_ADDR']            = '127.0.0.1';
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };


        $response = $makeRequest([
            'user_id' => $this->user->user_id,
            'birthdate' => '1990-01-12',
            'employee_id_char' => 'MGR001'
        ]);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);

        $reloadUser = User::where('user_id', $this->user->user_id)->first();
        $this->assertSame('MGR001', $reloadUser->employee()->first()->employee_id_char);

        $response = $makeRequest([
            'user_id' => $this->user->user_id,
            'lastname'  => 'Denear',
            'employee_id_char' => 'MGR001'
        ]);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);

        // User last name not updated -- 03-aug-15 CHANGED TO UPDATEABLE
        $reloadUser = User::where('user_id', $this->user->user_id)->first();
        $this->assertSame('Denear', $reloadUser->user_lastname);
    }

    public function testError_request_without_right_permission()
    {
        $i = 0;
        $makeRequest = function ($authData) use (&$i) {
            $_GET['apikey']       = $authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST = array_merge($_POST, [
                'user_id'   => $this->user->user_id,
                'birthdate' => '1990-01-12'
            ]);

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['REMOTE_ADDR']            = '127.0.0.1';
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url)->getContent();
            $response = json_decode($response);
            $i++;

            return $response;
        };

        $authData = Factory::create('Apikey');
        $response = $makeRequest($authData);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::ACCESS_DENIED, $response->code);

        $response = $makeRequest($this->authData);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);
    }
}
