<?php
/**
 * PHP Unit Test for DashboardAPIController#getTopWidgetClick
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getTopWidgetClickTest extends TestCase
{
    private $baseUrl = '/api/v1/dashboard/top-widget';

    protected $authData;
    protected $merchant;

    public static function prepareDatabase()
    {
        $merchant = Factory::create('Merchant');
        $retailer   = Factory::create('Retailer', ['parent_id' => $merchant->merchant_id]);
        $widgets  = [];
        $widgetTypes = ['new_product', 'catalogue', 'promotion', 'coupon'];
        $faker    = Faker::create();

        foreach ($widgetTypes as $type)
        {
            array_push($widgets, Factory::create('Widget', [
                'merchant_id' => $merchant->merchant_id,
                'widget_type' => $type
            ]));
        }

        $i=1;
        $prefix = DB::getTablePrefix();
        $insert = "INSERT INTO `{$prefix}activities` (`activity_id`, `group`, `activity_name`, `object_id`, `location_id`, `created_at`) VALUES";
        $id=1;
        foreach ($widgets as $widget)
        {
            $count = $i * 10;
            for ($j=0; $j<$count; $j++)
            {
                $created_at = $faker->dateTimeBetween('-1years', '+1years')->format('Y-m-d H:i:s');
                $insert .= "
                    ({$id}, 'mobile-ci', 'widget_click', '{$widget->widget_id}', '{$retailer->merchant_id}', '{$created_at}'),";
                $id++;
            }
            $i++;
        }
        $insert .= "(500, 'mobile-ci', 'widget_click', null, null, null);";

        DB::statement($insert);

        static::addData('widgets', $widgets);
        static::addData('merchant', $merchant);
        static::addData('authData', Factory::create('apikey_super_admin'));
    }

    public function testOK_get_top_widget_click()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['merchant_id']  = [$this->merchant->merchant_id];
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



    public function testOK_get_top_widgets_filtered_by_date()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['merchant_id']  = [$this->merchant->merchant_id];
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
            'is_report' => 1
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

    public function testOK_get_with_pagination()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['merchant_id']  = [$this->merchant->merchant_id];
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
            'take' => 2
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);

        $response = $makeRequest([
            'take' => 2,
            'skip' => 2
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        $response = $makeRequest([
            'take' => 2,
            'is_report'  => 1
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(2, $response->data->returned_records);

        $response = $makeRequest([
            'take' => 2,
            'skip' => 2,
            'is_report' => 1
        ]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(2, $response->data->returned_records);
        $this->assertSame(Status::OK_MSG, $response->message);
    }
}
