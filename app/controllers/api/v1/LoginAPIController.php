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
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class LoginAPIController extends ControllerAPI
{
    /**
     * POST - Login user
     *
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
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

            if (trim($email) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (trim($password) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'password'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = User::with('apikey', 'userdetail', 'role')
                        ->active()
                        ->where('user_email', $email)
                        ->first();

            if (! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($password, $user->user_password)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
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

    /**
     * POST - Logout user
     *
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postLogout()
    {
        // There is no exactly logout proses on the backend API
        return $this->render();
    }

    /**
     * POST - Register new customer
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`          (required) - Email address of the user
     * @return Illuminate\Support\Facades\Response
     */
    public function postRegisterUserInShop()
    {
        try {
            $httpCode = 200;

            $this->registerCustomValidation();

            $email = OrbitInput::post('email');

            $validator = Validator::make(
                array(
                    'email'     => $email,
                ),
                array(
                    'email'     => 'required|email|orbit.email.exists',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Begin database transaction
            $this->beginTransaction();

            $newuser = new User();
            $newuser->username = $email;
            $newuser->user_password = str_random(8);
            $newuser->user_email = $email;
            $newuser->status = 'pending';
            $newuser->user_role_id = Role::where('role_name','Consumer')->first()->role_id;
            $newuser->user_ip = $_SERVER['REMOTE_ADDR'];

            $newuser->save();

            $userdetail = new UserDetail();
            $userdetail = $newuser->userdetail()->save($userdetail);

            $newuser->setRelation('userdetail', $userdetail);
            $newuser->userdetail = $userdetail;

            // token
            $token = new Token();
            $token->token_name = 'user_registration_mobile';
            $token->token_value = $token->generateToken($email);
            $token->status = 'active';
            $token->email = $email;
            $token->expire = date('Y-m-d H:i:s', strtotime('+14 days'));
            $token->ip_address = $_SERVER['REMOTE_ADDR'];
            $token->user_id = $newuser->user_id;
            $token->save();

            // send the email
            Mail::send('emails.registration.activation-html', array('token' => $token->token_value, 'email' => $email), function($message)
            {
                $email = OrbitInput::post('email');
                $message->from('registration@dominopos.com', 'Orbit Registration')->subject('You are almost in Orbit!');
                $message->to($email);
            });

            $this->response->data = $newuser;

            // Commit the changes
            $this->commit();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * GET - Register Token Check
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `token`                 (required) - Token to be check
     * @return Illuminate\Support\Facades\Response
     */
    public function getRegisterTokenCheck()
    {
        try {
            $this->registerCustomValidation();
            
            $token_value = trim(OrbitInput::get('token'));

            $validator = Validator::make(
                array(
                    'token_value' => $token_value,
                ),
                array(
                    'token_value' => 'required|orbit.empty.token',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $token = App::make('orbit.empty.token');
            $user = User::where('user_id', $token->user_id)->first();

            if (! is_object($token) || ! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            Auth::Login($user);

            // update the token status so it cannot be use again
            $token->status = 'deleted';
            $token->save();

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

    protected function registerCustomValidation()
    {
        // Check user email address, it should not exists
        Validator::extend('orbit.email.exists', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->where('user_email', $value)
                        ->first();

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.user', $user);

            return TRUE;
        });

        // Check the existance of token
        Validator::extend('orbit.empty.token', function ($attribute, $value, $parameters) {
            $token = Token::active()->NotExpire()->where('token_value', $value)->first();

            if (empty($token)) {
                return FALSE;
            }

            App::instance('orbit.empty.token', $token);

            return TRUE;
        });
    }

}
