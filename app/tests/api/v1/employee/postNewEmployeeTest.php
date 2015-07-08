<?php
/**
 * PHP Unit Test for EmployeeAPIController#postNewEmployee
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postNewEmployeeTest extends TestCase
{
    protected $authData;
    protected $retailer;
    private $baseUrl  = '/api/v1/employee/new';

    public static function prepareDatabase()
    {
        $role = Factory::create('role_admin');
        $permission = Factory::create('Permission', ['permission_name' => 'create_employee']);
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $merchant = Factory::create('Merchant', ['user_id' => $user->user_id]);
        static::addData('authData', Factory::create('Apikey', ['user_id' => $user->user_id]));
        static::addData('coupons', Factory::times(3)->create('Coupon'));
        static::addData('merchant', $merchant);
        static::addData('retailer', Factory::create('Retailer', ['parent_id' => $merchant->merchant_id]));

        Factory::create('PermissionRole', ['role_id' => $role->role_id, 'permission_id' => $permission->permission_id]);
        Factory::create('Role', ['role_name' => 'cashier']);
    }

    public function testOK_create_employee_with_valid_parameters()
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

        $userCount     = User::count();
        $employeeCount = Employee::count();
        $response      = $makeRequest([
            'firstname' => 'John',
            'lastname'  => 'Doe',
            'username'  => 'john.doe',
            'password'  => 'john2doe',
            'birthdate' => '1990-01-12',
            'password_confirmation' => 'john2doe',
            'employee_role' => 'cashier',
            'retailer_ids'  => [$this->retailer->merchant_id]
        ]);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);

        // Should persist the user and their employee relations
        $this->assertSame($userCount + 1, User::count());
        $this->assertSame($employeeCount + 1, Employee::count());


        // Should be failed when retried after persisted
        // TODO: review behaviour this should be failed with username already exists
        /*$userCount     = User::count();
        $employeeCount = Employee::count();
        $response      = $makeRequest([
            'firstname' => 'John',
            'lastname'  => 'Doe',
            'username'  => 'john.doe',
            'password'  => 'john2doe',
            'password_confirmation' => 'john2doe',
            'employee_role' => 'cashier',
            'retailer_ids'  => [$this->retailer->merchant_id]
        ]);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);

        // Should not persist the user and their employee relations
        $this->assertSame($userCount, User::count());
        $this->assertSame($employeeCount, Employee::count());*/
    }

    public function testError_post_new_user_with_invalid_parameters()
    {
        $makeRequest = function ($postData) {
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST = $postData;

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

        $valid_request_data = [
            'firstname' => 'John',
            'lastname'  => 'Doe',
            'username'  => 'john.doe',
            'password'  => 'john2doe',
            'birthdate' => '1990-01-12',
            'password_confirmation' => 'john2doe',
            'employee_role' => 'cashier',
            'retailer_ids'  => [$this->retailer->merchant_id]
        ];

        $dataWithout = function ($key) use ($valid_request_data) {
            $result = $valid_request_data;
            unset($result[$key]);
            return $result;
        };

        $userCount     = User::count();
        $employeeCount = Employee::count();
        $required_fields_and_names = [
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'birthdate' => 'birthdate',
            'username' => 'Login.ID',
            'password' => 'password',
            'employee_role' => 'employee.role',
        ];

        foreach ($required_fields_and_names as $field => $name) {
            $bad_request_data = $dataWithout($field);
            $response = $makeRequest($bad_request_data);
            $this->assertResponseStatus(403);
            $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
            $this->assertRegExp("/${name}.*required/", $response->message);
        }

        $mismatched_password_data = $valid_request_data;
        $mismatched_password_data['password_confirmation'] = $mismatched_password_data['password'] . 'xxx';
        $response = $makeRequest($mismatched_password_data);
        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp("/password.*not.match/", $response->message);

        $future_birthdate_data = $valid_request_data;
        $future_birthdate_data['birthdate'] = date('Y-m-d', strtotime('+10 days'));
        $response = $makeRequest($future_birthdate_data);
        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp("/birthdate.*date.before/", $response->message);

        // At the end, no request should persist the user and their employee relations
        $this->assertSame($userCount, User::count());
        $this->assertSame($employeeCount, Employee::count());
    }

    public function testError_request_without_right_permission()
    {
        $i = 0;
        $makeRequest = function ($authData) use (&$i) {
            $_GET['apikey']       = $authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST = array_merge($_POST, [
                'firstname' => 'John',
                'lastname'  => 'Doe',
                'username'  => "john.{$i}.doe",
                'birthdate' => '1990-01-12',
                'password'  => 'john2doe',
                'password_confirmation' => 'john2doe',
                'employee_role' => 'cashier',
                'retailer_ids'  => [$this->retailer->merchant_id]
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