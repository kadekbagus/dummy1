<?php namespace MobileCI;

/**
 * An API controller for managing products.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use \View;
use \User;

class MobileCIAPIController extends ControllerAPI
{   
    /**
     * POST - Login customer in shop
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`          (required) - Email address of the user
     * @return Illuminate\Support\Facades\Response
     */
    public function postLoginInShop()
    {
        try {
            $email = trim(OrbitInput::post('email'));

            if (trim($email) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = User::with('apikey', 'userdetail', 'role')
                        ->active()
                        ->where('user_email', $email)
                        ->where('user_role_id', 3)
                        ->first();

            if (! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            if (! is_null($user)) {
                \Auth::Login($user);
                return \Redirect::to('/');
            }

            $this->response->data = $user;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }

    public function postSignUpView()
    {
        $email = OrbitInput::get('emailSignUp');
        // return View::make('mobile-ci.signup', compact($email));
        var_dump($email);
    }
}