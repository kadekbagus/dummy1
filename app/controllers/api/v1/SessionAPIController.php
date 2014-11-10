<?php
/**
 * An API controller for session user.
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class SessionAPIController extends ControllerAPI
{
    /**
     * POST - Session user
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `token`                 (required) - token of the user
     * @return Illuminate\Support\Facades\Response
     */
    public function getCheck($token)
    {
        try {

            // if token is not specified, then send invalid argument.
            if ($token == '') {
                $this->response->code = Status::INVALID_ARGUMENT;
                $this->response->status = 'error';
                $this->response->message = Lang::get('validation.required', array('attribute' => 'token'));
                $this->response->data = NULL;
            } else {

                // if the token is NOT in session, then send forbidden access.
                if (Session::get('_token') === $token) {

                    // if session has key 'login_xxx', then request ok. otherwise, session is expired.
                    if (Session::has(Auth::getName())) {
                        $this->response->code = Status::OK;
                        $this->response->status = 'success';
                        $this->response->message = Status::OK_MSG;
                        $this->response->data = NULL;
                    } else {
                        $this->response->code = Status::REQUEST_EXPIRED;
                        $this->response->status = 'error';
                        $this->response->message = Status::REQUEST_EXPIRED_MSG;
                        $this->response->data = NULL;
                    }
                } else {
                    ACL::throwAccessForbidden();
                }
            }
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }
}