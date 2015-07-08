<?php
/**
 * PHP Unit Test for SessionApiController#getCheck
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */

use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class getSessionCheckTest extends TestCase {

    private $baseUrl = '/api/v1/session/check';

    protected $useTruncate = false;
    protected $authData;
    private $events;

    public function setUp()
    {
        parent::setUp();
        $this->authData = Factory::create('apikey_super_admin');
        $this->events   = Factory::times(3)->create("EventModel");
    }

    public function testOK_get_check_valid_session()
    {
        $this->markTestIncomplete('This probably needs to login first and use the token');
        $makeRequest = function ($authData) {
            $_GET['apikey']       = $authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'GET';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };


        $response = call_user_func($makeRequest, $this->authData);

        // TODO: failed its superadmin with newly generated session but it said access forbidden
        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
    }

}
