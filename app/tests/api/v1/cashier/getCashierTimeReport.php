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

class getCashierTimeReport extends TestCase
{
    private  $baseUrl = "/api/v1/cashier/time-list";
    protected $users;
    protected $activities;

    public static function prepareDatabase()
    {
        // As User With Permission to update promotion on their store
        $role       = Factory::create('role_cashier');
        $admin      = Factory::create('role_admin');
        $user       = Factory::create('User', ['user_role_id' => $admin->role_id]);
        $authData   = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'view_cashier']);
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

        for ($i = 0; $i < 2; $i++) {
            /** @var $activity Activity */
            $activity = Factory::build('Activity_pos');
            $user  = $users[$i % 2];
            $activity->setUser($user)
                ->setActivityType('login')
                ->setActivityName('login_ok')
                ->setActivityNameLong('Login OK')
                ->responseOK();
            $activity->created_at = $faker->dateTimeBetween('2015-01-12 08:08:08', '2015-01-12 09:08:08');
            $activity->save(['force' => true]);
            array_push($activities, $activity);

            $activity = Factory::build('Activity_pos');
            $user  = $users[$i % 2];
            $activity->setUser($user)
                ->setActivityType('logout')
                ->setActivityName('logout_ok')
                ->setActivityNameLong('Logout OK')
                ->responseOK();
            $activity->created_at = $faker->dateTimeBetween('2015-01-12 15:08:08', '2015-01-12 18:08:08');
            $activity->save(['force' => true]);
            array_push($activities, $activity);

            $customer = Factory::create('User', [
                'user_firstname' => "Customer00{$i}",
                'user_lastname'  => "Jakarta"
            ]);

            $transactions = Factory::times(2)->create('Transaction', [
                'merchant_id'  => $merchants[$user->user_id],
                'retailer_id'  => $retailers[$user->user_id],
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
        }
    }

    public function testOK_get_cashier_time_list_without_additional_parameters()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };


        $response = $makeRequest([]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, $response->data->returned_records);
    }

    public function testOK_get_cashier_time_list_with_cashier_name_filter()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };


        $response = $makeRequest([
            'cashier_name' => 'Cashier001 Jakarta'
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, $response->data->returned_records);

        $response = $makeRequest([
            'cashier_name_like' => 'Cashier'
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, $response->data->returned_records);

        $response = $makeRequest([
            'cashier_name_like' => 'Bandung'
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, $response->data->returned_records);
    }

    public function testOK_get_cashier_time_list_with_customer_name_filter()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };


        $response = $makeRequest([
            'customer_firstname' => 'Customer001'
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, $response->data->returned_records);

        $response = $makeRequest([
            'customer_lastname' => 'Jakarta'
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, $response->data->returned_records);

        $response = $makeRequest([
            'customer_name_like' => 'Customer'
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, $response->data->returned_records);
    }

}
