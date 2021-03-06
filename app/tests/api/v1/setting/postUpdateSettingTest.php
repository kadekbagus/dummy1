<?php
/**
 * PHP Unit Test for SettingAPIController#postUpdateSetting
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */

use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postUpdateSettingTest extends TestCase
{
    private $baseUrl  = '/api/v1/setting/update';

    protected $authData;
    protected $settings;

    public static function prepareDatabase()
    {
        static::addData('authData', Factory::create('apikey_super_admin'));
        static::addData('settings', Factory::times(3)->create('Setting'));
    }

    public function testOK_update_settings_with_valid_data()
    {
        $makeRequest = function ($postData) {
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $_POST  = $postData;

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        //----------------------- CONTEXT: Valid Data --------
        $response = $makeRequest([
            'setting_name' => 'sample',
            'setting_value' => 10
        ]);

        // should be successful
        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);

        // should be persisted
        $currentSetting = Setting::where('setting_name', 'sample')->first();
        $this->assertSame('10', $currentSetting->setting_value);

        // should persists an activity
        // $latestActivity = Activity::where('object_id', $currentSetting->setting_id)->where('object_name', get_class($currentSetting))->first();
        // $this->assertSame('Update Setting OK', $latestActivity->activity_name);
    }

    public function testError_update_settings_without_required_parameter()
    {
        $makeRequest = function ($postData) {
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $_POST  = $postData;

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };

        //----------------------- CONTEXT: Without Setting Value --------
        $response = $makeRequest([
            'setting_name' => 'sample'
        ]);

        // should be successful
        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp("/setting.value.*is.required/i", $response->message);

        //----------------------- CONTEXT: Without Setting Name --------
        $response = $makeRequest([
            'setting_value' => 'sample'
        ]);

        // should be successful
        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp("/setting.name.*is.required/i", $response->message);
    }

}