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
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use Carbon\Carbon as Carbon;

class LoginAPIController extends ControllerAPI
{
    /**
     * POST - Login user
     *
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @deprecated
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     * @return Illuminate\Support\Facades\Response
     */
    public function postLogin()
    {
        $activity = Activity::portal()
                            ->setActivityType('login');
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

            $user = User::with('userdetail', 'role')
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

            // Successfull login
            $activity->setUser($user)
                     ->setActivityName('login_ok')
                     ->setActivityNameLong('Sign in')
                     ->responseOK();

            $this->response->data = $user;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->setModuleName('Application')->save();

        return $this->render();
    }

    /**
     * POST - Login for user with role 'Super Admin'
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postLoginAdmin()
    {
        $activity = Activity::portal()
                            ->setActivityType('login');
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

            $user = User::with('userdetail', 'role')
                        ->active()
                        ->where('user_email', $email)
                        ->first();

            if (! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // Only allowed for role 'Super Admin'
            if (! $user->isSuperAdmin()) {
                $message = Lang::get('validation.orbit.access.forbidden_role');
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($password, $user->user_password)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // Successfull login
            $activity->setUser($user)
                     ->setActivityName('login_ok')
                     ->setActivityNameLong('Sign in')
                     ->responseOK();

            $this->response->data = $user;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->setModuleName('Application')->save();

        return $this->render();
    }

    /**
     * POST - Login for user with role 'Merchant Owner'. Only allowed for role 'Super Admin' and 'Merchant Owner'
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postLoginMerchant()
    {
        $activity = Activity::portal()
                            ->setActivityType('login');
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

            $user = User::with('userdetail', 'role', 'merchants')
                        ->active()
                        ->whereHas('role', function($q) {
                            $q->whereIn('role_name', ['Super Admin', 'Merchant Owner']);
                        })
                        ->where('user_email', $email)
                        ->first();

            if (! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // Only allowed for role 'Super Admin' and 'Merchant Owner'
            if (! ($user->isSuperAdmin() OR $user->isMerchantOwner())) {
                $message = Lang::get('validation.orbit.access.forbidden_role');
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($password, $user->user_password)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // Successfull login
            $activity->setUser($user)
                     ->setActivityName('login_ok')
                     ->setActivityNameLong('Sign in')
                     ->responseOK();

            $this->response->data = $user;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->setModuleName('Application')->save();

        return $this->render();
    }

    /**
     * POST - Login for user with role 'Consumer'. Only allowed for role 'Super Admin', 'Merchant Owner', and 'Consumer'
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postLoginCustomer()
    {
        $activity = Activity::portal()
                            ->setActivityType('login');
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

            $user = User::with('userdetail', 'role')
                        ->active()
                        ->where('user_email', $email)
                        ->first();

            if (! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // Only allowed for role 'Super Admin', 'Merchant Owner', and 'Consumer'
            if (! ($user->isSuperAdmin() OR $user->isMerchantOwner() OR $user->isConsumer())) {
                $message = Lang::get('validation.orbit.access.forbidden_role');
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($password, $user->user_password)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // Successfull login
            $activity->setUser($user)
                     ->setActivityName('login_ok')
                     ->setActivityNameLong('Sign in')
                     ->responseOK();

            $this->response->data = $user;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->setModuleName('Application')->save();

        return $this->render();
    }

    public function getRetailerInfo()
    {
        try {
            $retailer_id = Config::get('orbit.shop.id');
            $retailer = \Retailer::with('parent')->where('merchant_id', $retailer_id)->first();

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
     * POST - Login cashier in shop
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `username`  (required) - username of the cashier
     * @param string    `password`  (required) - password of the cashier
     * @return Illuminate\Support\Facades\Response
     */
    public function postLoginCashier()
    {
        $activity = Activity::pos()->setActivityType('login');
        try {
            $username = OrbitInput::post('username');
            $password = OrbitInput::post('password');

            $retailer = $this->getRetailerInfo();

            $role = Role::where('role_name', 'cashier')->first();
            if (empty($role)) {
                $message = Lang::get('validation.orbit.empty.employee.role',
                    array('role' => 'Cashier')
                );
                ACL::throwAccessForbidden($message);
            }

            $user = User::with('role', 'employee')
                        ->active('users')
                        ->where('username', $username)
                        ->where('user_role_id', $role->role_id)
                        ->employeeMerchantIds([$retailer->parent_id])
                        ->first();

            $merchant = $retailer->parent;

            // This should be not happens unless someone messing up the database
            // manually
            if (empty($user->employee)) {
                $message = Lang::get('Internal error, employee object is empty.');
                ACL::throwAccessForbidden($message);
            }

            // Check to make sure the cashier only can login to current merchant
            // on the box
            $cashierMerchantIds = $user->employee->getMyMerchantIds();
            if (! in_array($merchant->merchant_id, $cashierMerchantIds)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            if (is_object($user)) {
                if (! Hash::check($password, $user->user_password)) {
                    $message = \Lang::get('validation.orbit.access.loginfailed');
                    ACL::throwAccessForbidden($message);
                } else {
                    // Start the orbit session
                    $data = array(
                        'logged_in' => TRUE,
                        'user_id'   => $user->user_id,
                    );
                    $config = new SessionConfig(Config::get('orbit.session'));
                    $session = new Session($config);
                    $session->enableForceNew()->start($data);

                    // Successfull login
                    $activity->setUser($user)
                             ->setActivityName('login_ok')
                             ->setActivityNameLong('Login OK')
                             ->responseOK();
                }
            } else {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            $user->setHidden(array('user_password', 'apikey'));

            $result['user'] = $user;
            $result['merchant'] = $merchant;

            $this->response->data = $result;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        $activity->save();

        return $this->render();
    }

    /**
     * POST - Login for user with role 'Consumer'. Only allowed for role 'Super Admin', 'Merchant Owner', and 'Consumer'. For login from Mobile-CI.
     *
     * @param string    `email`          (required) - Email address of the user
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Tian <tian@dominopos.com>
     */
    public function postLoginMobile()
    {
        try {
            $email = trim(OrbitInput::post('email'));

            if (trim($email) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            $retailer = $this->getRetailerInfo();

            $user = User::with('userdetail', 'role')
                        ->excludeDeleted()
                        ->where('user_email', $email)
                        ->whereHas(
                            'role',
                            function ($query) {
                                $query->where(function ($query) {
                                    $query->where('role_name', 'Consumer')
                                        ->orWhere('role_name', 'Super Admin')
                                        ->orWhere('role_name', 'Merchant Owner');
                                });
                            }
                        )
                        ->first();

            if (! is_object($user)) {
                $response = \LoginAPIController::create('raw')->postRegisterUserInShop();
                if ($response->code !== 0) {
                    throw new Exception($response->message, $response->code);
                }
                $user = $response->data;
            }

            $user_detail = UserDetail::where('user_id', $user->user_id)->first();
            $user_detail->last_visit_shop_id = $retailer->merchant_id;
            $user_detail->last_visit_any_shop = Carbon::now();
            $user_detail->save();

            $cart = Cart::where('status', 'active')->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->first();
            if (is_null($cart)) {
                $cart = new Cart();
                $cart->customer_id = $user->user_id;
                $cart->merchant_id = $retailer->parent_id;
                $cart->retailer_id = $retailer->merchant_id;
                $cart->status = 'active';
                $cart->save();
                $cart->cart_code = Cart::CART_INCREMENT + $cart->cart_id;
                $cart->save();
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

    /**
     * GET - Logout customer in shop
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Tian <tian@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Redirect
     */
    public function getLogoutMobile()
    {
        try {
            $this->prepareSession();

            $this->session->start(array(), 'no-session-creation');
            $this->session->destroy();
        } catch (Exception $e) {
        }

        return \Redirect::to('/customer');
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
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                     (required) - Email address of the user
     * @return Illuminate\Support\Facades\Response
     */
    public function postRegisterUserInShop()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('registration');
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

            $customerRole = Role::where('role_name', 'Consumer')->first();
            if (empty($customerRole)) {
                $errorMessage = Lang::get('validation.orbit.empty.consumer_role');
                throw new Exception($errorMessage);
            }

            // The retailer (shop) which this registration taken
            $retailerId = Config::get('orbit.shop.id');
            $retailer = Retailer::excludeDeleted()
                                ->where('merchant_id', $retailerId)
                                ->first();
            if (empty($retailer)) {
                $errorMessage = Lang::get('validation.orbit.empty.retailer');
                throw new Exception($errorMessage);
            }

            $newuser = new User();
            $newuser->username = strtolower($email);
            $newuser->user_email = strtolower($email);
            $newuser->status = 'pending';
            $newuser->user_role_id = $customerRole->role_id;
            $newuser->user_ip = $_SERVER['REMOTE_ADDR'];

            $newuser->save();

            $userdetail = new UserDetail();

            // Fill the information about retailer (shop)
            $userdetail->merchant_id = $retailer->parent_id;
            $userdetail->merchant_acquired_date = date('Y-m-d H:i:s');
            $userdetail->retailer_id = $retailer->merchant_id;

            // Save the user details
            $userdetail = $newuser->userdetail()->save($userdetail);

            // Generate API key for this user
            $apikey = $newuser->createApiKey();

            $newuser->setRelation('userDetail', $userdetail);
            $newuser->user_detail = $userdetail;

            $newuser->setRelation('apikey', $apikey);
            $newuser->apikey = $apikey;

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
            $token->user_id = $newuser->user_id;
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

            $this->response->data = $newuser;

            // Commit the changes
            if (Config::get('orbit.registration.mobile.fake') !== TRUE) {
                $this->commit();
            }

            // Successfull registration
            $activity->setUser($newuser)
                     ->setActivityName('registration_ok')
                     ->setActivityNameLong('Sign Up')
                     ->setModuleName('Application')
                     ->responseOK();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Registration
            $activity->setUser('guest')
                     ->setActivityName('registration_failed')
                     ->setActivityNameLong('Registration Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Registration
            $activity->setUser('guest')
                     ->setActivityName('registration_failed')
                     ->setActivityNameLong('Registration Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
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

            // Failed Registration
            $activity->setUser('guest')
                     ->setActivityName('registration_failed')
                     ->setActivityNameLong('Registration Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            // Rollback the changes
            $this->rollBack();

            // Failed Registration
            $activity->setUser('guest')
                     ->setActivityName('registration_failed')
                     ->setActivityNameLong('Registration Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * GET - Register Token Check
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `token`                     (required) - Token to be check
     * @param string    `password`                  (required) - Password for the account
     * @param string    `password_confirmation`     (required) - Confirmation
     * @return Illuminate\Support\Facades\Response
     */
    public function postRegisterTokenCheck()
    {
        $activity = Activity::portal()
                            ->setActivityType('activation');
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
                    'token_value'   => 'required|orbit.empty.token',
                    'password'      => 'required|min:5|confirmed',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $token = App::make('orbit.empty.token');
            $user = User::with('userdetail')
                        ->excludeDeleted()
                        ->where('user_id', $token->user_id)
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

            if (Config::get('orbit.registration.mobile.send_welcome_email') === TRUE) {
                // Sign page link
                $signinUrl = Config::get('orbit.registration.mobile.signin_url');

                $data = array(
                    'email'         => $user->user_email,
                    'password'      => $password,
                    'signin_url'    => $signinUrl
                );
                $mailviews = array(
                    'html' => 'emails.registration.activated-html',
                    'text' => 'emails.registration.activated-text'
                );
                Mail::send($mailviews, $data, function($message) use ($user)
                {
                    $emailconf = Config::get('orbit.registration.mobile.sender');
                    $from = $emailconf['email'];
                    $name = $emailconf['name'];

                    $message->from($from, $name)->subject('Your Account on Orbit has been Activated!');
                    $message->to($user->user_email);
                });
            }

            // Commit the changes
            $this->commit();

            // Successfull activation
            $activity->setUser($user)
                     ->setActivityName('activation_ok')
                     ->setActivityNameLong('Account Activation')
                     ->setModuleName('Application')
                     ->responseOK();
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('guest')
                     ->setActivityName('activation_failed')
                     ->setActivityNameLong('Account Activation Failed')
                     ->setModuleName('Application')
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('guest')
                     ->setActivityName('activation_failed')
                     ->setActivityNameLong('Account Activation Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('guest')
                     ->setActivityName('activation_failed')
                     ->setActivityNameLong('Account Activation Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->save();

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
