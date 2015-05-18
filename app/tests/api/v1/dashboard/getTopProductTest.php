<?php
/**
 * PHP Unit Test for DashboardAPIController#getTopProduct
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class getTopProductTest extends TestCase
{
    private $baseUrl = '/api/v1/dashboard/top-product';

    protected $authData;
    protected $merchant;

    public static function prepareDatabase()
    {
        $merchant = Factory::create('Merchant');
        $products = Factory::times(5)->create('Product', ['merchant_id' => $merchant->merchant_id]);

        $i=1;
        $prefix = DB::getTablePrefix();
        $insert = "INSERT INTO `{$prefix}activities` (`activity_id`, `activity_name`, `product_id`) VALUES";
        $id=1;
        foreach ($products as $product)
        {
            $count = $i * 10;
            for ($j=0; $j<$count; $j++)
            {
                $insert .= "
                    ({$id},'view_product', {$product->product_id}),";
                $id++;
            }
            $i++;
        }
        $insert .= "(500, 'view_product', null);";

        DB::statement($insert);

        static::addData('products', $products);
        static::addData('merchant', $merchant);
        static::addData('authData', Factory::create('apikey_super_admin'));
    }

    public function testOK_get_top_product()
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
}
