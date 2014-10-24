<?php
/**
 * A dummy API controller for testing purpose.
 */
use OrbitShop\API\v1\ControllerAPI;

class DummyAPIController extends ControllerAPI
{
    public function __construct($contentType='application/json')
    {
        parent::__construct($contentType);
        $this->pdo = DB::connection()->getPdo();
    }

    public function hisName()
    {
        $name = new stdclass();
        $name->first_name = 'John';
        $name->last_name = 'Smith';
        $this->response->data = $name;

        return $this->render();
    }

    public function hisNameAuth()
    {
        try {
            // Require authentication
            $this->checkAuth();

            $name = new stdclass();
            $name->first_name = 'John';
            $name->last_name = 'Smith';
            $this->response->data = $name;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
        }

        return $this->render();
    }

    public function myName()
    {
        $name = new stdclass();
        $name->first_name = Input::post('firstname');
        $name->last_name = Input::post('lastname');
        $this->response->data = $name;

        return $this->render;
    }

    public function myNameAuth()
    {
        try {
            $name = new stdclass();
            $name->first_name = Input::post('firstname');
            $name->last_name = Input::post('lastname');
            $this->response->data = $name;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
        }

        return $this->render;
    }
}
