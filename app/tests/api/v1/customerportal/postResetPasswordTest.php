<?php
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

/**
 * Test Customer portal's "reset password" (the actual reset process, not the email).
 * 
 * @property User active_user
 * @property User pending_user
 */
class postResetPasswordTest extends TestCase {

    private $baseUrl = '/api/v1/customerportal/reset-password';


    public static function prepareDatabase()
    {
        foreach (['active', 'pending'] as $status) {
            $user = Factory::create('user_consumer', ['status' => $status]);
            static::addData("{$status}_user", $user);
        }
    }

    /**
     * Should reject password reset if the token is not found.
     */
    public function testResetNoSuchToken()
    {
        $_POST = [];
        $_POST['token'] = 'nonexistent';
        $_POST['password'] = 'abc123';
        $_POST['password_confirmation'] = 'abc123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->baseUrl;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $this->baseUrl);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('error', $json_response->status);
    }

    /**
     * Should accept password reset if the token is valid and the customer is active.
     */
    public function testResetCustomerActive()
    {
        $user_token = Factory::create('token_reset_password', ['user_id' => $this->active_user->user_id]);
        $_POST = [];
        $_POST['token'] = $user_token->token_value;
        $_POST['password'] = 'abc123';
        $_POST['password_confirmation'] = 'abc123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->baseUrl;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $this->baseUrl);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('success', $json_response->status);
    }

    /**
     * Should only accept password reset if the customer is active.
     *
     * Password reset for customer with pending status should be rejected.
     */
    public function testResetCustomerPending()
    {
        $user_token = Factory::create('token_reset_password', ['user_id' => $this->pending_user->user_id]);
        $_POST = [];
        $_POST['token'] = $user_token->token_value;
        $_POST['password'] = 'abc123';
        $_POST['password_confirmation'] = 'abc123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->baseUrl;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $this->baseUrl);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('error', $json_response->status);
    }

    /**
     * Should only accept password reset if the passwords match.
     */
    public function testResetWithoutMatchingPassword()
    {
        $user_token = Factory::create('token_reset_password', ['user_id' => $this->active_user->user_id]);
        $_POST = [];
        $_POST['token'] = $user_token->token_value;
        $_POST['password'] = 'abc123';
        $_POST['password_confirmation'] = 'def456';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->baseUrl;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $this->baseUrl);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('error', $json_response->status);
    }

    /**
     * After being used, the token should not be able to be used again.
     */
    public function testCannotResetTwiceUsingSameToken()
    {
        $user_token = Factory::create('token_reset_password', ['user_id' => $this->active_user->user_id]);
        $_POST = [];
        $_POST['token'] = $user_token->token_value;
        $_POST['password'] = 'abc123';
        $_POST['password_confirmation'] = 'abc123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->baseUrl;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $this->baseUrl);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('success', $json_response->status);
        $_POST = [];
        $_POST['token'] = $user_token->token_value;
        $_POST['password'] = 'abc123';
        $_POST['password_confirmation'] = 'abc123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->baseUrl;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $this->baseUrl);
        $this->assertResponseOk();
        $json_response = json_decode($response->getContent());
        $this->assertSame('error', $json_response->status);
    }
}