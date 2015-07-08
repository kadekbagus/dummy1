<?php
/**
 * PHP Unit Test for CashierAPIController#getCashierTimeReport
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getCashierTimeReportPrintViewTest extends TestCase
{
    private  $baseUrl = "/printer/cashier/time-list";
    protected $useIntermediate = true;
    protected $users;
    protected $activities;
    protected $merchants;
    private   $headerNum = 6;

    public static function prepareDatabase()
    {
        // As User With Permission to update promotion on their store
        $role       = Factory::create('role_cashier');
        $admin      = Factory::create('role_admin');
        $user       = Factory::create('User', ['user_role_id' => $admin->role_id]);
        $authData   = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'view_employee']);
        Factory::create('PermissionRole', ['role_id' => $admin->role_id, 'permission_id' => $permission->permission_id]);
        Factory::create('PermissionRole', ['role_id' => $role->role_id, 'permission_id' => $permission->permission_id]);

        $cashier_1        = Factory::create('User', [
            'user_role_id'   => $role->role_id,
            'user_firstname' => 'Cashier001',
            'user_lastname'  => 'Jakarta'
        ]);

        $cashier_2        = Factory::create('User', [
            'user_role_id' => $role->role_id,
            'user_firstname' => 'Cashier002',
            'user_lastname'  => 'Bandung'
        ]);

        $users            = [$cashier_1, $cashier_2];
        $faker            = Faker::create();
        $merchants        = [];
        $retailers        = [];
        $activities       = [];

        /** @var  $user User */
        foreach ($users as $user) {
            $user_id = $user->user_id;
            $merchants[$user_id] = Factory::create('Merchant', ['user_id' => $user_id]);
            $retailers[$user_id] = Factory::create('Retailer', ['user_id' => $user_id, 'parent_id' => $merchants[$user_id]->merchant_id]);
        }

        $prefix = DB::getTablePrefix();
        $insert_activities = "INSERT INTO `{$prefix}activities` (`activity_id`, `group`, `activity_type`, `activity_name`, `activity_name_long`, `user_id`, `location_id`, `created_at`) VALUES";
        $activity_id=1;

        for ($i = 0; $i < 2; $i++) {
            /** @var $activity Activity */
            $activity = Factory::build('Activity_pos');
            $user  = $users[$i % 2];
            // login ok
            $login_time = $faker->dateTimeBetween('2015-01-12 08:08:08', '2015-01-12 09:08:08')->format('Y-m-d H:i:s');
            $logout_time =  $faker->dateTimeBetween('2015-01-12 15:08:08', '2015-01-12 18:08:08')->format('Y-m-d H:i:s');
            $insert_activities .=
                "({$activity_id}, 'pos', 'login', 'login_ok', 'Login OK', {$user->user_id}, {$retailers[$user->user_id]->merchant_id}, '{$login_time}'),";
            $activity_id++;
            $insert_activities .=
                "({$activity_id}, 'pos', 'logout', 'logout_ok', 'Logout OK', {$user->user_id}, {$retailers[$user->user_id]->merchant_id}, '{$logout_time}'),";
            $activity_id++;

            $customer = Factory::create('User', [
                'user_firstname' => "Customer00{$i}",
                'user_lastname'  => "Jakarta"
            ]);

            $transactions = Factory::times(2)->create('Transaction', [
                'merchant_id'  => $merchants[$user->user_id]->merchant_id,
                'retailer_id'  => $retailers[$user->user_id]->merchant_id,
                'cashier_id'   => $user->user_id,
                'total_to_pay' => 100000,
                'customer_id'  => $customer->user_id
            ]);

            foreach ($transactions as $transaction) {
                Factory::create('TransactionDetail', [
                    'transaction_id' => $transaction->transaction_id
                ]);
            }

            static::addData('authData', $authData);
            static::addData('activities', $activities);
            static::addData('merchants', $merchants);
        }
        $insert_activities .=
            "(5000, 'pos', 'logout', 'logout_ok', 'Logout OK', null, null, null)";

        DB::statement($insert_activities);

    }

    public function testOK_get_cashier_time_list_without_additional_parameters()
    {
        $makeRequest = function ($getData) {
            $_GET                    = array_merge($_GET, $getData);
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();
            $_GET['apikey']          = $this->authData->api_key;
            $_GET['apitimestamp']    = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", $response));
            return $response;
        };


        $response = $makeRequest([]);

        $this->assertResponseOk();

        $this->assertSame(2 + $this->headerNum, count($response));
    }

    public function testOK_get_cashier_time_list_with_merchant_id_filters()
    {
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();
            $_GET['apikey']          = $this->authData->api_key;
            $_GET['apitimestamp']    = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", $response));
            return $response;
        };


        foreach ($this->merchants as $userId=>$merchant) {
            $response = $makeRequest([
                'merchant_id' => $merchant->merchant_id
            ]);

            $this->assertResponseOk();

            $this->assertSame(2 + $this->headerNum, count($response));
        }
    }

    public function testOK_get_cashier_time_list_with_cashier_name_filter()
    {
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();
            $_GET['apikey']          = $this->authData->api_key;
            $_GET['apitimestamp']    = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", $response));
            return $response;
        };

        $response = $makeRequest([
            'cashier_name' => 'Cashier001 Jakarta'
        ]);

        $this->assertResponseOk();

        $this->assertSame(2 + $this->headerNum, count($response));

        $response = $makeRequest([
            'cashier_name_like' => 'Cashier'
        ]);

        $this->assertResponseOk();

        $this->assertSame(2 + $this->headerNum, count($response));

        $response = $makeRequest([
            'cashier_name_like' => 'Bandung'
        ]);

        $this->assertResponseOk();

        $this->assertSame(2 + $this->headerNum, count($response));
    }

    public function testOK_get_cashier_time_list_with_customer_name_filter()
    {
        $this->markTestIncomplete('Customer name filtering not yet implemented');
        $makeRequest = function ($getData) {
            $_GET                    = $getData;
            $_GET['export']          = 'csv';
            $_GET['orbit_session']   = $this->session->getSessionId();
            $_GET['apikey']          = $this->authData->api_key;
            $_GET['apitimestamp']    = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            $response = array_map('str_getcsv', explode("\n", $response));
            return $response;
        };


        $response = $makeRequest([
            'customer_firstname' => 'Customer001'
        ]);

        $this->assertResponseOk();

        $this->assertSame(1 + $this->headerNum, count($response));

        $response = $makeRequest([
            'customer_lastname' => 'Jakarta'
        ]);

        $this->assertResponseOk();

        $this->assertSame(2 + $this->headerNum, count($response));

        $response = $makeRequest([
            'customer_name_like' => 'Customer'
        ]);

        $this->assertResponseOk();

        $this->assertSame(2 + $this->headerNum, count($response));
    }

    public function testOK_get_print_product_list_without_additional_parameters()
    {
        $makeRequest = function ($getData) {
            $_GET                    = array_merge($_GET, $getData);
            $_GET['orbit_session']   = $this->session->getSessionId();
            $_GET['apikey']          = $this->authData->api_key;
            $_GET['apitimestamp']    = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            ob_start();
            $this->call('GET', $url)->getContent();
            $response = ob_get_contents();
            ob_end_clean();

            return $response;
        };
        $response = $makeRequest(['export' => 'print']);

        $this->assertResponseOk();
    }

}
