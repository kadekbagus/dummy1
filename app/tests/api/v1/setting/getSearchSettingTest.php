<?php
/**
 * PHP Unit Test for SettingAPIController#postSearchSetting
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */

use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class getSearchSettingTest extends TestCase
{
    private $baseUrl  = '/api/v1/setting/search';

    protected $authData;
    protected $settings;

    public static function prepareDatabase()
    {
        static::addData('authData', Factory::create('apikey_super_admin'));
        static::addData('settings', Factory::times(3)->create('Setting'));
    }

    public function testOK_get_search_setting_without_parameters()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
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
        $this->assertSame(3, $response->data->total_records);
        $this->assertSame(3, $response->data->returned_records);
    }


    public function testOK_get_search_setting_with_setting_name()
    {
        Factory::create('Setting', ['setting_name' => 'Searchable Unique', 'status' => 'active']);

        $makeRequest = function ($getData) {
            $_GET                 = $getData;
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

        //-------------------- CONTEXT: Settings name as array -----------
        $response = $makeRequest(['setting_name' => ['Searchable Unique']]);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame('Searchable Unique', $response->data->records[0]->setting_name);


        //---------------- CONTEXT: Settings Name Like -------------------------
        $response = $makeRequest(['setting_name_like' => 'Searchable']);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
        $this->assertSame(1, $response->data->total_records);
        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame('Searchable Unique', $response->data->records[0]->setting_name);
    }
}