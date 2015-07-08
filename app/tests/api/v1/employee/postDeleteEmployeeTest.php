<?php
/**
 * PHP Unit Test for EmployeeAPIController#postDeleteEmployee
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postDeleteEmployeeTest extends TestCase
{
    protected $authData;
    protected $retailer;
    protected $adminUser;
    private $baseUrl  = '/api/v1/employee/delete';

    public static function prepareDatabase()
    {
        $role       = Factory::create('role_admin');
        $permission = Factory::create('Permission', ['permission_name' => 'delete_employee']);
        $admin_user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $merchant   = Factory::create('Merchant', ['user_id' => $admin_user->user_id]);

        static::addData('authData', Factory::create('Apikey', ['user_id' => $admin_user->user_id]));
        static::addData('retailer', Factory::create('Retailer', ['parent_id' => $merchant->merchant_id]));
        static::addData('merchant', $merchant);
        static::addData('adminUser', $admin_user);

        Factory::create('PermissionRole', ['role_id' => $role->role_id, 'permission_id' => $permission->permission_id]);
        Factory::create('UserDetail', ['user_id' => $admin_user->user_id]);
    }


    public function testOK_post_delete_employee_with_valid_parameters()
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

        $employee = Factory::create('employee_cashier');
        $response = $makeRequest(['user_id' => $employee->user_id]);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
    }

    public function testError_request_without_right_permission()
    {
        $makeRequest = function ($authData, $user_id) {
            $_GET['apikey']       = $authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST = array_merge($_POST, [
                'user_id'   => $user_id,
            ]);

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['REMOTE_ADDR']            = '127.0.0.1';
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $employee = Factory::create('employee_cashier');

        $authData = Factory::create('Apikey');
        $response = $makeRequest($authData, $employee->user_id);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::ACCESS_DENIED, $response->code);

        $response = $makeRequest($this->authData, $employee->user_id);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);
    }
}