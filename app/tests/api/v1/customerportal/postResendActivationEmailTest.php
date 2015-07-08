<?php
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

/**
 * Test Customer portal's "resend activation email".
 * 
 * @property User active_user
 * @property User pending_user
 */
class postResendActivationEmailTest extends TestCase {



    public static function prepareDatabase()
    {
        $merchant = Factory::create('Merchant');
        $retailer = Factory::create('Retailer', ['parent_id' => $merchant->merchant_id]);

        $active_user = Factory::create('user_consumer', ['status' => 'active']);
        /** @var \UserDetail $active_user_detail */
        $active_user_detail = Factory::create('UserDetail', ['user_id' => $active_user->user_id]);
        $active_user_detail->retailer_id = $retailer->merchant_id;
        $active_user_detail->save();

        static::addData('active_user', $active_user);
        static::addData('active_user_detail', $active_user_detail);

        $pending_user = Factory::create('user_consumer', ['status' => 'pending']);
        $pending_user_detail = Factory::create('UserDetail', ['user_id' => $pending_user->user_id]);
        $pending_user_detail->retailer_id = $retailer->merchant_id;
        $pending_user_detail->save();

        static::addData('pending_user', $pending_user);
        static::addData('pending_user_detail', $pending_user_detail);
    }

    public function testResendNoSuchEmail()
    {
        Mail::shouldReceive('send')->never();
        $url = '/api/v1/customerportal/resend-activation-email';
        $_POST = [];
        $_POST['email'] = 'ironman@localhost.org';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('error', $json_response->status);
    }

    public function testResendCustomerAlreadyActive()
    {
        Mail::shouldReceive('send')->never();
        $url = '/api/v1/customerportal/resend-activation-email';
        $_POST = [];
        $_POST['email'] = $this->active_user->user_email;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('error', $json_response->status);
    }

    public function testResendCustomerPending()
    {
        Mail::shouldReceive('send')->once();
        $url = '/api/v1/customerportal/resend-activation-email';
        $_POST = [];
        $_POST['email'] = $this->pending_user->user_email;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('success', $json_response->status);
    }
}