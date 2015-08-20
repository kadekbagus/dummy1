<?php namespace Customerportal;

/**
 * An API controller for managing products.
 */
use App;
use Exception;
use Hash;
use \Mail;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Illuminate\Database\QueryException;
use \View;
use \User;
use \UserDetail;
use \Token;
use \Role;
use \Lang;
use \Apikey;
use \Validator;
use \Config;
use \Retailer;
use \Product;
use Carbon\Carbon as Carbon;
use \stdclass;
use \Category;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Cart;
use \CartDetail;

class CustomerportalAPIController extends ControllerAPI
{
    protected $session = NULL;

    /**
     * POST - Login customer portal
     *
     * @author Agung Julisman <agung@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`          (required) - Email address of the user
     * @param string    `password`       (required) - password of the cashier
     * @return \Illuminate\Support\Facades\Response
     */
    public function postLoginInPortal()
    {
        try {

            $email    = trim(OrbitInput::post('email'));
            $password = trim(OrbitInput::post('password'));

            if (trim($email) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            if (trim($password) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'password'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = User::with('apikey', 'userdetail', 'role')
                ->excludeDeleted()
                ->where('user_email', $email)
                ->where('status', 'active')
                ->whereHas('role', function($query)
                {
                    $query->where('role_name','Consumer');
                })
                ->first();

            if (is_object($user)) {
               if( ! \Hash::check($password, $user->user_password)){
                   $message = \Lang::get('validation.orbit.access.loginfailed');
                   ACL::throwAccessForbidden($message);
               }else{
                   // Start the orbit session
                   $data = array(
                       'logged_in' => TRUE,
                       'user_id'   => $user->user_id,
                   );
                   $config = new SessionConfig(Config::get('orbit.session'));
                   $session = new Session($config);
                   $session->enableForceNew()->start($data);

                   $sessionHeader = $session->getSessionConfig()->getConfig('session_origin.header.name');
                   $sessionHeader = 'Set-' . $sessionHeader;
                   $this->customHeaders[$sessionHeader] = $session->getSessionId();
               }
            } else {
                $message = \Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            $user->setHidden(array('user_password', 'apikey'));
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

    public function getLogoutInShop()
    {
        try {
            $this->prepareSession();

            $this->session->start(array(), 'no-session-creation');
            $this->session->destroy();
        } catch (Exception $e) {
        }

        return \Redirect::to('/customer');
    }



    public function getRetailerInfo()
    {
        try {
            // $this->checkAuth();
            // $user = $this->api->user;
            // if (! ACL::create($user)->isAllowed('view_retailer')) {
            //     $errorlang = Lang::get('validation.orbit.actionlist.view_retailer');
            //     $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorlang));
            //     ACL::throwAccessForbidden($message);
            // }

            $retailer_id = Config::get('orbit.shop.id');
            $retailer = Retailer::with('parent')->where('merchant_id', $retailer_id)->first();
            return $retailer;
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
    }

    /**
     * Resend the activation email.
     *
     * Just in case the user deletes it. This will expire the previous token and issue a new one.
     *
     * Precondition: user status is pending.
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                     (required) - Email address of the user
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author William Shallum <william@dominopos.com>
     */
    public function postResendActivationEmail()
    {
        try {

            $email = trim(OrbitInput::post('email'));

            if (trim($email) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // we don't resend to users that are already active
            $user = User::with('apikey', 'userdetail', 'role')
                ->excludeDeleted()
                ->where('user_email', $email)
                ->where('status', 'pending')
                ->whereHas('role', function($query)
                {
                    $query->where('role_name','Consumer');
                })
                ->first();

            if (!is_object($user))
            {
                $errorMessage = \Lang::get('validation.orbit.empty.customer_email');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->beginTransaction();

            // delete old tokens
            $old_tokens = Token::active()
                ->NotExpire()
                ->where('token_name', 'user_registration_mobile')
                ->where('user_id', $user->user_id)
                ->get();
            /** @var Token $old_token */
            foreach ($old_tokens as $old_token)
            {
                $old_token->delete();
            }


            // then create a new one

            $retailer = Retailer::find($user->userdetail->retailer_id);
            if (!is_object($retailer))
            {
                // TODO is this possible? may need to have generic email view not mentioning any retailer
                OrbitShopAPI::throwInvalidArgument(); // TODO message?
            }

            // Token expiration, fallback to 30 days
            $expireInDays = Config::get('orbit.registration.mobile.activation_expire', 30);

            // Token Settings
            $token = new Token();
            $token->token_name = 'user_registration_mobile';
            $token->token_value = $token->generateToken($email);
            $token->status = 'active';
            $token->email = $email;
            $token->expire = date('Y-m-d H:i:s', strtotime('+' . $expireInDays . ' days'));
            $token->ip_address = $_SERVER['REMOTE_ADDR'];
            $token->user_id = $user->user_id;
            $token->save();

            // URL Activation link
            $baseUrl = Config::get('orbit.registration.mobile.activation_base_url');
            $tokenUrl = sprintf($baseUrl, $token->token_value);
            $contactInfo = Config::get('orbit.contact_information.customer_service');

            $data = array(
                'token'             => $token->token_value,
                'email'             => $email,
                'token_url'         => $tokenUrl,
                'shop_name'         => $retailer->name,
                'cs_phone'          => $contactInfo['phone'],
                'cs_email'          => $contactInfo['email'],
                'cs_office_hour'    => $contactInfo['office_hour']
            );
            $mailviews = array(
                'html' => 'emails.registration.activation-html',
                'text' => 'emails.registration.activation-text'
            );
            Mail::send($mailviews, $data, function($message)
            {
                $emailconf = Config::get('orbit.registration.mobile.sender');
                $from = $emailconf['email'];
                $name = $emailconf['name'];

                $email = OrbitInput::post('email');
                $message->from($from, $name)->subject('You are almost in Orbit!');
                $message->to($email);
            });

            // Commit the changes
            if (Config::get('orbit.registration.mobile.fake') !== TRUE) {
                $this->commit();
            }
        } catch (InvalidArgsException $e) {
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        return $this->render();
    }

    /**
     * POST - reset customer password. Requires token.
     *
     * Token must be a reset_password token.
     *
     * List of API Parameters
     * ----------------------
     * @param string    `token`                     (required) - Valid password reset token value for customer
     * @param string    `password`                  (required) - Password
     * @param string    `password_confirmation`     (required) - Password confirmation
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author William Shallum <william@dominopos.com>
     */
    public function postResetPassword()
    {
        try {
            $this->registerCustomValidation();

            $tokenValue = trim(OrbitInput::post('token'));
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');

            $validator = Validator::make(
                array(
                    'token_value'   => $tokenValue,
                    'password'      => $password,
                    'password_confirmation' => $password2
                ),
                array(
                    'token_value'   => 'required|orbit.empty.reset_password.token',
                    'password'      => 'required|min:5|confirmed',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $token = App::make('orbit.empty.reset_password.token');
            $user = User::with('userdetail')
                ->excludeDeleted()
                ->where('user_id', $token->user_id)
                ->where('status', 'active')
                ->whereHas('role', function($query)
                {
                    $query->where('role_name','Consumer');
                })
                ->first();

            if (! is_object($token) || ! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // Begin database transaction
            $this->beginTransaction();

            // update the token status so it cannot be use again
            $token->status = 'deleted';
            $token->save();

            // Update user password and activate them
            $user->user_password = Hash::make($password);
            $user->status = 'active';
            $user->save();

            $this->response->message = Lang::get('statuses.orbit.updated.your_password');
            $this->response->data = $user;

            // Commit the changes
            $this->commit();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

        }

        return $this->render();
    }

    /**
     * POST - request password reset. Sends email with token.
     *
     * Customer must be active. If customer is pending, user should "resend activation", not reset password.
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                     (required) - Email for active customer.
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author William Shallum <william@dominopos.com>
     */
    public function postRequestPasswordReset()
    {
        try {
            $this->beginTransaction();
            // get user
            $email = trim(OrbitInput::post('email'));
            if (trim($email) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            $user = User::with('apikey', 'userdetail', 'role')
                ->excludeDeleted()
                ->where('user_email', $email)
                ->where('status', 'active')
                ->whereHas('role', function($query)
                {
                    $query->where('role_name','Consumer');
                })
                ->first();
            if (!is_object($user)) {
                $errorMessage = \Lang::get('validation.orbit.empty.customer_email');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // remove all existing reset tokens
            $existing_tokens = Token::active()
                ->NotExpire()
                ->where('token_name', 'reset_password')
                ->where('user_id', $user->user_id)
                ->get();
            foreach ($existing_tokens as $existing_token) {
                $existing_token->delete();
            }

            // Token expiration, fallback to 30 days
            $expireInDays = Config::get('orbit.reset_password.reset_expire', 7);

            // Token Settings
            $token = new Token();
            $token->token_name = 'reset_password';
            $token->token_value = $token->generateToken($email);
            $token->status = 'active';
            $token->email = $email;
            $token->expire = date('Y-m-d H:i:s', strtotime('+' . $expireInDays . ' days'));
            $token->ip_address = $_SERVER['REMOTE_ADDR'];
            $token->user_id = $user->user_id;
            $token->save();

            // URL Activation link
            $baseUrl = Config::get('orbit.reset_password.reset_base_url');
            $tokenUrl = sprintf($baseUrl, $token->token_value);
            $contactInfo = Config::get('orbit.contact_information.customer_service');

            $data = array(
                'token'             => $token->token_value,
                'email'             => $email,
                'token_url'         => $tokenUrl,
                'cs_phone'          => $contactInfo['phone'],
                'cs_email'          => $contactInfo['email'],
                'cs_office_hour'    => $contactInfo['office_hour']
            );
            $mailviews = array(
                'html' => 'emails.reset-password.customer-html',
                'text' => 'emails.reset-password.customer-text'
            );
            Mail::send($mailviews, $data, function($message)
            {
                $emailconf = Config::get('orbit.reset_password.sender');
                $from = $emailconf['email'];
                $name = $emailconf['name'];

                $email = OrbitInput::post('email');
                $message->from($from, $name)->subject('Password Reset Request');
                $message->to($email);
            });

            $this->commit();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

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
        Validator::extend('orbit.empty.reset_password.token', function ($attribute, $value, $parameters) {
            $token = Token::active()
                ->NotExpire()
                ->where('token_value', $value)
                ->where('token_name', Token::NAME_RESET_PASSWORD)
                ->first();

            if (empty($token)) {
                return FALSE;
            }

            App::instance('orbit.empty.reset_password.token', $token);

            return TRUE;
        });
    }


    /**
     * Get current logged in user used in view related page.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return User $user
     */
    protected function getLoggedInUser()
    {
        $this->prepareSession();

        $userId = $this->session->read('user_id');
        if ($this->session->read('logged_in') !== TRUE || ! $userId) {
            throw new Exception ('Invalid session data.');
        }

        $user = User::with('userDetail')->find($userId);

        if (! $user) {
            throw new Exception ('Session error: user not found.');
        }

        return $user;
    }

    /**
     * Prepare session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    protected function prepareSession()
    {
        if (! is_object($this->session)) {
            // This user assumed are Consumer, which has been checked at login process
            $config = new SessionConfig(Config::get('orbit.session'));
            $this->session = new Session($config);
            $this->session->start();
        }
    }


}
