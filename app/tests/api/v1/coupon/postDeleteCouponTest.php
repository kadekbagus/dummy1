<?php
/**
 * PHP Unit Test for CouponApiController#postDeleteCoupon
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postDeleteCouponTest extends TestCase
{
    private $baseUrl  = '/api/v1/coupon/delete';

    public static function prepareDatabase()
    {
        $merchant  = Factory::create('Merchant');
        static::addData('authData',  Factory::create('apikey_super_admin'));
        static::addData('promotions',  Factory::times(3)->create('Coupon'));
        static::addData('merchant',  $merchant);
        static::addData('retailer',  Factory::create('Retailer', ['parent_id' => $merchant->merchant_id]));
    }

    public function testOK_post_delete_promotion()
    {
        $coupon = Factory::create('Coupon');
        Factory::create('CouponRule', ['promotion_id' => $coupon->promotion_id]);
        $couponCountBefore = Coupon::count();

        $makeRequest = function ($changes = []) use ($coupon) {
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST = $changes;
            $_POST['promotion_id'] = $coupon->promotion_id;

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url, $_POST)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = call_user_func($makeRequest, ['promotion_name' => 'Changed']);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertRegExp('/coupon.*deleted/i', $response->message);

        // should change number of promotion
        $this->assertSame($couponCountBefore - 1, Coupon::excludeDeleted()->count());
    }

    public function testACL_post_delete_promotion()
    {
        $coupon = Factory::create('Coupon');
        Factory::create('CouponRule', ['promotion_id' => $coupon->promotion_id]);

        $makeRequest = function ($authData, $promo = false) use ($coupon) {
            $_GET['apikey']       = $authData->api_key;
            $_GET['apitimestamp'] = time();

            if ($promo) {
                $coupon = $promo;
            }

            $_POST['promotion_id'] = $coupon->promotion_id;

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url, $_POST)->getContent();
            $response = json_decode($response);

            return $response;
        };


        // As Super Admin
        $response = call_user_func($makeRequest, $this->authData);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertRegExp('/coupon.*deleted/i', $response->message);

        // As User Without Granted Permission
        $merchant   = $coupon->merchant()->first();
        $authData   = Factory::create('Apikey', ['user_id' => $merchant->user_id]);

        $response   = call_user_func($makeRequest, $authData);

        // should be failed
        $this->assertResponseStatus(403);

        // should be access denied
        $this->assertSame(Status::ACCESS_DENIED, $response->code);
        $this->assertRegExp('/you.do.not.have.permission.to/i', $response->message);

        // As User With Permission to update promotion on their store
        $user       = Factory::create('User');
        $authData   = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'delete_coupon']);
        $merchant   = Factory::create('Merchant', ['user_id' => $user->user_id]);

        $coupon = Coupon::excludeDeleted()->first();
        $coupon->merchant_id = $merchant->merchant_id;
        $coupon->save();

        Factory::create('PermissionRole', ['role_id' => $user->user_role_id, 'permission_id' => $permission->permission_id]);

        $response = call_user_func($makeRequest,  $authData, $coupon);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertRegExp('/coupon.*deleted/i', $response->message);


        // should not change number of promotion
        // we delete 2 promotions
        $this->assertSame(2, Coupon::excludeDeleted()->count());
    }

    public function testError_parameters_post_delete_promotion()
    {
        $coupon = Factory::create('Coupon');
        Factory::create('CouponRule', ['promotion_id' => $coupon->promotion_id]);

        $makeRequest = function ($postData) {
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST = $postData;

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url, $_POST)->getContent();
            $response = json_decode($response);

            return $response;
        };


        // post without parameters
        $response = call_user_func($makeRequest, []);

        // should be failed
        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/promotion.id.*is.required/', $response->message);

        if (false) { // uuid
            // post with merchant id not number
            $response = call_user_func($makeRequest, [
                'promotion_id' => 'abc'
            ]);

            // should be failed
            $this->assertResponseStatus(403);
            $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
            $this->assertRegExp('/promotion.id.must.be.a.number/', $response->message);
        }
    }
}
