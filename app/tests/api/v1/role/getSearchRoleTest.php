<?php
/**
 * PHP Unit Test for RoleAPIController#getSearchRole
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */

use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
class getSearchRoleTest extends TestCase
{
    protected $useTruncate = false;

    private $baseUrl = '/api/v1/role/list';
    private $authData;
    private $roles;

    public function setUp()
    {
        parent::setUp();
        $this->authData = Factory::create('Apikey', ['user_id' => 'factory:user_super_admin']);
        $this->roles = Factory::times(6)->create('Role');
    }

    public function testOK_search_role_without_parameter()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = $makeRequest([]);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
        $this->assertSame(7, $response->data->total_records);
    }

    public function testOK_get_search_role_with_certain_roles_name()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = $makeRequest(['role_names' => ['Super Admin']]);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
        $this->assertSame(1, $response->data->total_records);
    }

    public function testOK_get_search_role_for_certain_ids()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = $makeRequest(['role_ids' => [Role::first()->role_id]]);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
        $this->assertSame(1, $response->data->total_records);
    }

    public function testOK_get_search_role_with_their_permissions()
    {
        $role = Factory::create('Role', ['role_name' => 'Super Copter']);
        $permission = Factory::create('Permission', ['permission_name' => 'update_settings']);

        Factory::create('PermissionRole', ['permission_id' => $permission->permission_id, 'role_id' => $role->role_id]);

        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = $makeRequest(['with' => ['permissions'], 'role_ids' => [$role->role_id]]);

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
        $this->assertSame(1, $response->data->total_records);

        $this->assertSame('update_settings', $response->data->records[0]->permissions[0]->permission_name);
    }

    public function testError_get_search_role_as_guest()
    {
        $role       = Factory::create('role_admin');
        $permission = Factory::create('Permission', ['permission_name' => 'update_category']);
        $user       = Factory::create('User', ['user_role_id' => $role->role_id]);
        $authData   = Factory::create('Apikey', ['user_id' => $user->user_id]);

        Factory::create('PermissionRole', ['permission_id' => $permission->permission_id, 'role_id' => $role->role_id]);

        $makeRequest = function ($authData) {
            $_GET['apikey']       = $authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = $makeRequest($authData);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::ACCESS_DENIED, $response->code);
        $this->assertRegExp('/you.do.not.*access.*view.role/i', $response->message);
    }
}