<?php
/**
 * An API controller for login user.
 */
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
    function postLogin()
    {
        try {
            $email = OrbitInput::post('email');
            $password = OrbitInput::post('password');

            if (Auth::attempt(array('user_email' => $email, 'password' => $password, 'status' => 'active'))) {
                $user = User::with('apikey', 'userdetail')->find(Auth::user()->user_id);
                $user->setHidden(array('user_password'));
                $this->response->data = $user->toArray();
            } else {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
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

    function postLogout()
    {
        Auth::logout();
    }
}