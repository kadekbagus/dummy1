<?php
/**
 * PHP Unit Test for EventApiController#postUpdateEvent
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */

use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;


class postUpdateEventTest extends TestCase {

    private $baseUrl = '/api/v1/event/update';

    protected $authData;

    protected $event;

    public static function prepareDatabase()
    {
        static::addData('authData', Factory::create('apikey_super_admin'));
        static::addData('event', Factory::create('EventModel'));
    }

    public function testOK_update_event_with_valid_parameters()
    {
        $makeRequest = function($postData) {

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

        $postData                   = [];
        $postData['event_id']       = $this->event->event_id;
        $postData['event_name']     = 'Unique Submitted Event';
        $postData['event_type']     = 'link';
        $postData['status']         = 'active';
        $postData['description']    = 'Description for event here';
        $postData['begin_date']     = date('Y-m-d h:i:s', strtotime('+1 day'));

        $response = call_user_func($makeRequest, $postData);

        $currentEvent = EventModel::where('event_id', $this->event->event_id)->first();

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // Should update the event
        $this->assertSame('Unique Submitted Event', $currentEvent->event_name);


        //----------------------- CONTEXT ---------------------------------

        $postData['status']  = 'pending';

        $response = call_user_func($makeRequest, $postData);

        $currentEvent = EventModel::where('event_id', $this->event->event_id)->first();

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // Should update the event
        $this->assertSame('pending', $currentEvent->status);

    }

    public function testError_post_update_with_invalid_parameters()
    {
        $makeRequest = function($postData) {

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

        $response = call_user_func($makeRequest, []);

        // Should not be OK
        $this->assertResponseStatus(403);

        // should say Invalid Arguments
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/event.id.*required/', $response->message);


        //---------------------------------- CONTEXT -----------------
        $response = call_user_func($makeRequest, [
            'event_id' => $this->event->event_id,
            'merchant_id' => 'abc'
        ]);

        $this->assertResponseStatus(403);

        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/merchant.id.*be.a.number/', $response->message);


        //---------------------------------- CONTEXT -----------------
        $response = call_user_func($makeRequest, [
            'event_id' => $this->event->event_id,
            'event_name' => 'abc'
        ]);

        $this->assertResponseStatus(403);

        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/event.name.*be.a.*5.char/', $response->message);



        //---------------------------------- CONTEXT -----------------

        Factory::create('EventModel', ['event_name' => 'Stack Event']);

        $response = call_user_func($makeRequest, [
            'event_id' => $this->event->event_id,
            'event_name' => 'Stack Event'
        ]);

        $this->assertResponseStatus(403);

        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/event.name.*used/', $response->message);
    }


    public function testACL_post_update_event()
    {
        $makeRequest = function($authData, $event) {
            $_GET['apikey']       = $authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST['event_id']       = $event->event_id;
            $_POST['event_type']     = 'link';
            $_POST['status']         = 'active';
            $_POST['description']    = 'Description for event here';
            $_POST['begin_date']     = date('Y-m-d h:i:s', strtotime('+1 day'));

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url, $_POST)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $user       = Factory::create('User');
        $authData   = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'update_event']);
        $merchant   = Factory::create('Merchant', ['user_id' => $user->user_id]);
        $event      = Factory::create('EventModel', ['merchant_id' => $merchant->merchant_id]);

        Factory::create('PermissionRole', ['role_id' => $user->user_role_id, 'permission_id' => $permission->permission_id]);

        $response = call_user_func($makeRequest, $authData, $event);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);

        //----------------------------------CONTEXT----------------
        $user       = Factory::create('User');
        $authData   = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'create_event']);

        Factory::create('PermissionRole', ['role_id' => $user->user_role_id, 'permission_id' => $permission->permission_id]);

        $response = call_user_func($makeRequest, $authData, $this->event);

        $this->assertResponseStatus(403);

        $this->assertSame(Status::ACCESS_DENIED, $response->code);
    }
}