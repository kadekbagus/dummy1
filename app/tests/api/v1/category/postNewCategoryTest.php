<?php
/**
 * PHP Unit Test for Category Controller postNewCategory
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */

use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postNewCategoryTest extends TestCase
{
    private $baseUrl = '/api/v1/family/new/';

    public function setUp()
    {
        parent::setUp();

        $this->authData   = Factory::create('Apikey', ['user_id' => 'factory:user_super_admin']);
        $this->category   = Factory::create('Category');
    }

    public function testError_post_category_without_auth_data()
    {
        $data          = new stdclass();
        $data->code    = Status::CLIENT_ID_NOT_FOUND;
        $data->status  = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data    = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $this->baseUrl)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testError_post_new_category_with_invalid_auth_data()
    {
        $data          = new stdclass();
        $data->code    = Status::INVALID_SIGNATURE;
        $data->status  = 'error';
        $data->message = Status::INVALID_SIGNATURE_MSG;
        $data->data    = NULL;

        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature('invalid', 'sha256');

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testError_post_new_category_without_required_parameter()
    {

        $data          = new stdclass();
        $data->code    = Status::INVALID_SIGNATURE;
        $data->status  = 'error';
        $data->message = Status::INVALID_SIGNATURE_MSG;
        $data->data    = NULL;

        $_POST['apikey']       = $this->authData->api_key;
        $_POST['apitimestamp'] = time();

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $this->baseUrl;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $expect = json_encode($data);
        $return = $this->call('POST', $this->baseUrl, $_POST)->getContent();
        $this->assertSame($expect, $return);
    }
}