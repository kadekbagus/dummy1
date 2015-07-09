<?php
use Laracasts\TestDummy\Factory;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;

/**
 * Tests categories properly added.
 *
 * @property Merchant $merchant
 * @property MerchantTax $merchantTax
 * @property Category[] $categories
 */
class postUpdateProduct_CategoryTest extends TestCase
{
    public static function prepareDatabase()
    {
        static::addData('merchant', $merchant = Factory::create('Merchant'));
        $permission = Factory::create('Permission', ['permission_name' => 'update_product']);
        Factory::create('PermissionRole',
            ['role_id' => $merchant->user->user_role_id, 'permission_id' => $permission->permission_id]);
        static::addData('authData', Factory::create('Apikey', ['user_id' => $merchant->user->user_id]));

        static::addData('merchantTax', Factory::create('MerchantTax', ['merchant_id' => $merchant->merchant_id]));

        $categories = [];
        for ($level = 1; $level <= 5; $level++) {
            $categories[] = Factory::create('Category', ['category_level' => $level, 'merchant_id' => $merchant->merchant_id, 'created_by' => $merchant->user_id, 'modified_by' => $merchant->user_id]);
        }

        static::addData('categories', $categories);

    }

    private function makeRequest($product, $postdata)
    {
        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];

        $_POST = array_merge([
            'product_id' => $product->product_id,
            'product_name' => $product->product_name,
            'product_code' => $product->product_code,
            'upc_code' => $product->upc_code,
            'price' => $product->price,
            'status' => $product->status,
            'short_description' => $product->short_description,
            'merchant_tax_id1' => $product->merchant_tax_id1,
        ], $postdata);

        $url = '/api/v1/product/update?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    private function assertJsonResponseOk($response)
    {
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertSame(0, (int)$response->code);
    }

    private function assertJsonResponseMatches($expected_code, $expected_status, $expected_message, $response)
    {
        $this->assertSame($expected_message, $response->message);
        $this->assertSame($expected_status, $response->status);
        $this->assertSame($expected_code, (int)$response->code);
    }

    private function assertJsonResponseMatchesRegExp(
        $expected_code,
        $expected_status,
        $expected_message_regexp,
        $response
    ) {
        $this->assertRegExp($expected_message_regexp, $response->message);
        $this->assertSame($expected_status, $response->status);
        $this->assertSame($expected_code, (int)$response->code);
    }

    /**
     * @return Product
     */
    private function createProduct()
    {
        return Factory::create('Product', [
            'merchant_id' => $this->merchant->merchant_id,
            'merchant_tax_id1' => $this->merchantTax->merchant_tax_id
        ]);
    }


    public function testUpdateProductCategory()
    {
        for ($max_level = 1; $max_level <= 5; $max_level++) {
            $product = $this->createProduct();
            $data = [];
            for ($level = 1; $level <= $max_level; $level++) {
                $param_name = 'category_id' . $level;
                $data[$param_name] = $this->categories[$level - 1]->category_id;
            }
            $response = $this->makeRequest($product, $data);
            $this->assertJsonResponseOk($response);
            for ($level = 1; $level <= $max_level; $level++) {
                $returned_name = 'category' . $level;
                $this->assertObjectHasAttribute($returned_name, $response->data);
                $returned_object = $response->data->{$returned_name};
                $this->assertNotNull($returned_object);
                $this->assertSame((string)$this->categories[$level - 1]->category_id, $returned_object->category_id);
            }
        }
    }

}
