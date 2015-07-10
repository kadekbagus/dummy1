<?php
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

/**
 * Test Customer portal's "request password reset".
 * 
 * @property User active_user
 * @property User pending_user
 * @property User admin_user
 */
class postRequestPasswordResetTest extends TestCase {

    private $baseUrl = '/api/v1/customerportal/request-password-reset';

    public static function prepareDatabase()
    {
        $active_user = Factory::create('user_consumer', ['status' => 'active']);
        static::addData('active_user', $active_user);

        $pending_user = Factory::create('user_consumer', ['status' => 'pending']);
        static::addData('pending_user', $pending_user);

        $admin_user = Factory::create('user_super_admin', ['status' => 'active']);
        static::addData('admin_user', $admin_user);
    }

    /**
     * Should reject password reset requests if the email is unknown.
     */
    public function testRequestNoSuchEmail()
    {
        Mail::shouldReceive('send')->never();
        $_POST = [];
        $_POST['email'] = 'ironman@localhost.org';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->baseUrl;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $this->baseUrl);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('error', $json_response->status);
    }

    /**
     * Should only accept password reset requests for active customers.
     *
     * Test that customer with pending status is rejected.
     */
    public function testRequestCustomerStillPending()
    {
        Mail::shouldReceive('send')->never();
        $_POST = [];
        $_POST['email'] = $this->pending_user->user_email;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->baseUrl;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $this->baseUrl);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('error', $json_response->status);
    }

    /**
     * Should only accept password reset requests for active customers.
     */
    public function testRequestCustomerActive()
    {
        Mail::shouldReceive('send')->once();
        $_POST = [];
        $_POST['email'] = $this->active_user->user_email;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->baseUrl;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $this->baseUrl);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('success', $json_response->status);
    }

    /**
     * Should not accept password reset requests for users without Consumer role.
     */
    public function testRequestForAdmin()
    {
        Mail::shouldReceive('send')->never();
        $_POST = [];
        $_POST['email'] = $this->admin_user->user_email;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->baseUrl;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $this->baseUrl);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('error', $json_response->status);
    }
}