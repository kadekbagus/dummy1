<?php
/**
 * An API controller for login user.
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class LoginAPIController extends ControllerAPI
{
    /**
     * POST - Login user
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     * @return Illuminate\Support\Facades\Response
     */
    public function postLogin()
    {
        try {
            $email = trim(OrbitInput::post('email'));
            $password = trim(OrbitInput::post('password'));

            if ($email == '') {
                $this->response->code = Status::INVALID_ARGUMENT;
                $this->response->status = 'error';
                $this->response->message = Lang::get('validation.required', array('attribute' => 'email'));
                $this->response->data = NULL;
            } elseif ($password == '') {
                $this->response->code = Status::INVALID_ARGUMENT;
                $this->response->status = 'error';
                $this->response->message = Lang::get('validation.required', array('attribute' => 'password'));
                $this->response->data = NULL;
            } else {
                if (Auth::attempt(array('user_email' => $email, 'password' => $password, 'status' => 'active'))) {
                    $user = User::with('apikey', 'userdetail')->find(Auth::user()->user_id);
                    $user->setHidden(array('user_password'));
                    $this->response->data = $user->toArray();
                } else {
                    $message = Lang::get('validation.orbit.access.loginfailed');
                    ACL::throwAccessForbidden($message);
                }
            }
        } catch (ACLForbiddenException $e) {
            $this->response->code = Status::ACCESS_DENIED;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }

    /**
     * POST - Logout user
     *
     * @author Tian <tian@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postLogout()
    {
        try {
            // if user is login, then logout the user, otherwise throw access forbidden.
            if (Auth::check()) {
                Auth::logout();

                // if the user is still in login, then throw unknown error.
                if (Auth::check()) {
                    $this->response->code = Status::UNKNOWN_ERROR;
                    $this->response->status = 'error';
                    $this->response->message = Status::UNKNOWN_ERROR_MSG;
                    $this->response->data = NULL;
                }
            } else {
                ACL::throwAccessForbidden();
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