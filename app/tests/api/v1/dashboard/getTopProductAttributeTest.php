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

class getTopProductAttributeTest extends TestCase
{
    private $baseUrl = '/api/v1/dashboard/top-product-attribute';

    protected $authData;
    protected $merchant;

    public static function prepareDatabase()
    {
        $faker      = Faker::create();
        $merchant   = Factory::create('Merchant');
        $products   = Factory::times(5)->create('Product', ['merchant_id' => $merchant->merchant_id]);

        $product_attributes = Factory::times(4)->create('ProductAttribute', [
            'merchant_id' => $merchant->merchant_id,
        ]);

        foreach ($product_attributes as $attr) {
            for ($i = 0; $i <= 5; $i++) {
                $name = $faker->word(2);
                $name = "{$attr->product_attribute_name} : {$name}";
                Factory::create('ProductAttributeValue', [
                    'value' => $name,
                    'product_attribute_id' => $attr->product_attribute_id,
                ]);
            }
        }

        $createVariant = function ($product, $attributes, $nums)
        {
            $nums = min($nums, count($attributes));
            $variant = Factory::build('ProductVariant');
            $variant->product_id = $product->product_id;
            $variant->price = $product->price;
            $variant->upc = $product->upc_code;
            $variant->sku = $product->product_code;
            $variant->merchant_id = $product->merchant_id;
            $variant->created_by = $product->created_by;
            $variant->modified_by = $product->modified_by;
            for ($i = 0; $i <= $nums - 1; $i++) {
                $seq         = $i+1;
                $attr_seq    = "attribute_id{$seq}";
                $variant_seq = "product_attribute_value_id{$seq}";
                $product->$attr_seq = $attributes[$i]->product_attribute_id;
                $value       = $attributes[$i]->values()->orderBy(DB::raw('RAND()'))->first();
                $variant->$variant_seq = $value->product_attribute_value_id;
            }
            $variant->save();
            $product->save();
        };

        $i=1;
        $prefix = DB::getTablePrefix();
        $insert = "INSERT INTO `{$prefix}activities` (`activity_id`, `activity_name`, `product_id`) VALUES";
        $id=1;
        foreach ($products as $product)
        {
            ProductVariant::createDefaultVariant($product);
            $createVariant($product, $product_attributes, $i);
            $count = $i * 10;
            for ($j=0; $j<$count; $j++)
            {
                $insert .= "
                    ({$id},'view_product', '{$product->product_id}'),";
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
        $this->markTestIncomplete('Not implemented: get top product attribute');

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
        $this->markTestIncomplete('Not implemented: get top product attribute');

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
