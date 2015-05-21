<?php
/**
 * PHP Unit Test for DashboardAPIController#getUserByGender
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getUserByGenderTest extends TestCase
{
    private $baseUrl = '/api/v1/dashboard/user-by-age';

    protected $authData;
    protected $merchant;

    public static function prepareDatabase()
    {
        $faker         = Faker::create();
        $_users        = Factory::times(5)->create('User');
        $male_users    = Factory::times(5)->create('User');
        $female_users  = Factory::times(5)->create('User', ['created_at' => $faker->dateTimeBetween('-2days', '-1day')]);

        $users = [];

        foreach($_users as $user)
        {
            Factory::create('UserDetail', [
                'user_id' => $user->user_id,
                'birthdate' => $faker->dateTimeBetween('-30years', '-27years')
            ]);
            array_push($users, $user);
        }

        foreach ($male_users as $user)
        {
            Factory::create('UserDetail', [
                'user_id' => $user->user_id,
                'gender'  => 'm',
                'birthdate' => $faker->dateTimeBetween('-25years', '-20years')
            ]);

            array_push($users, $user);
        }

        foreach ($female_users as $user)
        {
            Factory::create('UserDetail', [
                'user_id' => $user->user_id,
                'gender'  => 'f',
                'birthdate' => $faker->dateTimeBetween('-19years', '-18years')
            ]);
            array_push($users, $user);
        }


        static::addData('users', $users);
        static::addData('authData', Factory::create('apikey_super_admin'));
    }

    public function testOK_get_user_by_age()
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

        $response = $makeRequest([
            'is_report' => 1
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
    }



    public function testOK_get_top_user_login_filtered_by_date()
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
            'begin_date' => date('Y-m-d H:i:s', time())
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);

        $response = $makeRequest([
            'end_date' => date('Y-m-d H:i:s', time())
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        $response = $makeRequest([
            'begin_date' => date('Y-m-d H:i:s', time()),
            'is_report'  => 1
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);

        $response = $makeRequest([
            'end_date' => date('Y-m-d H:i:s', time()),
            'is_report' => 1
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
    }
}
