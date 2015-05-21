<?php
/**
 * PHP Unit Test for DashboardAPIController#getTopProductFamily
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getTopProductFamilyTest extends TestCase
{
    private $baseUrl = '/api/v1/dashboard/top-product-family';

    protected $authData;
    protected $merchant;

    public static function prepareDatabase()
    {
        $faker      = Faker::create();
        $merchant   = Factory::create('Merchant');
        $categories = [];
        for($i=5;$i>0;$i--)
        {
            $cat = Factory::times(5)->create('Category', [
                'merchant_id'    => $merchant->merchant_id,
                'category_level' => $i
            ]);
            foreach ($cat as $c)
            {
                array_push($categories, $c);
            }
        }
        $i=1;
        $prefix = DB::getTablePrefix();
        $insert = "INSERT INTO `{$prefix}activities` (`activity_id`, `activity_name`, `object_id`) VALUES";
        $id=1;
        foreach ($categories as $category)
        {
            $count = $i * 10;
            for ($j=0; $j<$count; $j++)
            {
                $insert .= "
                    ({$id},'view_category', {$category->category_id}),";
                $id++;
            }
            $i++;
        }
        $insert .= "(5000, 'view_category', null);";

        DB::statement($insert);

        static::addData('categories', $categories);
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
        $this->assertSame(Status::OK_MSG, $response->message);
    }


    public function testOK_get_top_product_filtered_by_date()
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
    }
}
