<?php namespace MobileCI;

/**
 * An API controller for managing products.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
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
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = User::with('apikey', 'userdetail', 'role')
                        ->active()
                        ->where('user_email', $email)
                        ->whereHas('role', function($query)
                            {
                                $query->where('role_name','Consumer');
                            })
                        ->first();

            if (! is_object($user)) {
                $message = \Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            } else {
                \Auth::login($user);
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

    public function getLogoutInShop()
    {
        \Auth::logout();
        return \Redirect::to('/customer');
    }


    // public function postCheckEmail()
    // {
    //     try {
    //         $email = trim(OrbitInput::post('email'));

    //         if (trim($email) === '') {
    //             $errorMessage = \Lang::get('validation.required', array('attribute' => 'email'));
    //             OrbitShopAPI::throwInvalidArgument($errorMessage);
    //         }

    //         $validator = Validator::make(
    //             array(
    //                 'email' => $email,
    //             ),
    //             array(
    //                 'email' => 'email',
    //             )
    //         );
            
    //         if ($validator->fails()) {
    //             $errorMessage = $validator->messages()->first();
    //             OrbitShopAPI::throwInvalidArgument($errorMessage);
    //         }

    //         $user = User::active()
    //                     ->where('user_email', $email)
    //                     ->whereHas('role', function($query)
    //                         {
    //                             $query->where('role_name','Consumer');
    //                         })
    //                     ->first();

    //         if (! is_object($user)) {
    //             $message = \Lang::get('validation.orbit.access.loginfailed');
    //             ACL::throwAccessForbidden($message);
    //         }

    //         $this->response->data = $user;
    //     } catch (ACLForbiddenException $e) {
    //         $this->response->code = $e->getCode();
    //         $this->response->status = 'error';
    //         $this->response->message = $e->getMessage();
    //         $this->response->data = null;
    //     } catch (InvalidArgsException $e) {
    //         $this->response->code = $e->getCode();
    //         $this->response->status = 'error';
    //         $this->response->message = $e->getMessage();
    //         $this->response->data = null;
    //     } catch (Exception $e) {
    //         $this->response->code = $e->getCode();
    //         $this->response->status = 'error';
    //         $this->response->message = $e->getMessage();
    //         $this->response->data = null;
    //     }

    //     return $this->render();
    // }

    public function postSignUpView()
    {
        $email = trim(OrbitInput::post('emailSignUp'));
        if(is_null($email)){
            $email = '';
        } else {
            $validator = \Validator::make(
                array(
                    'email' => $email,
                ),
                array(
                    'email' => 'email',
                )
            );
        }
        
        if ($validator->fails()) {
            $errorMessage = $validator->messages()->first();
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }
        $retailer = $this->getRetailerInfo();
        return View::make('mobile-ci.signup', array('email'=>$email, 'retailer'=>$retailer));
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

            $validator = \Validator::make(
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
            $newuser->status = 'active';
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

            $apikey = new Apikey();
            $apikey->api_key = Apikey::genApiKey($newuser);
            $apikey->api_secret_key = Apikey::genSecretKey($newuser);
            $apikey->status = 'active';
            $apikey->user_id = $newuser->user_id;
            $apikey = $newuser->apikey()->save($apikey);

            // send the email
            \Mail::send('emails.registration.activation-html', array('token' => $token->token_value, 'email' => $email), function($message)
            {
                $email = OrbitInput::post('email');
                $message->from('registration@dominopos.com', 'Orbit Registration')->subject('You are almost in Orbit!');
                $message->to($email);
            });

            // authenticate user
            \Auth::login($newuser);

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
                $this->response->message = \Lang::get('validation.orbit.queryerror');
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

    public function getHomeView()
    {
        try {
            $this->checkAuth();
            $user = $this->api->user;
            if (! ACL::create($user)->isAllowed('view_product')) {
                // $errorlang = Lang::get('validation.orbit.actionlist.view_product');
                // $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorlang));
                // ACL::throwAccessForbidden($message);
                return \Redirect::route('signin');
            }
            $retailer = $this->getRetailerInfo();
            $new_products = Product::with('media')->where('new_from','<=', Carbon::now())->where('new_until', '>=', Carbon::now())->get();
            return View::make('mobile-ci.home', array('page_title'=>Lang::get('mobileci.page_title.home'), 'retailer'=>$retailer, 'new_products'=>$new_products));
            
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

    public function getSignInView()
    {
        try {
            $retailer = $this->getRetailerInfo();
            return View::make('mobile-ci.signin', array('retailer'=>$retailer));
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

    public function getSignUpView()
    {
        try {
            $retailer = $this->getRetailerInfo();
            return View::make('mobile-ci.signup', array('email'=>'', 'retailer'=>$retailer));
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

    public function getCatalogueView()
    {
        try {
            $retailer = $this->getRetailerInfo();
            return View::make('mobile-ci.catalogue', array('page_title'=>Lang::get('mobileci.page_title.catalogue'), 'retailer'=>$retailer));
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

    public function getSearchProduct()
    {
        try {
            // Require authentication
            $this->checkAuth();

            $user = $this->api->user;
        
            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.product.getsearchproduct.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
           
            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:product_name,product_price',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );
            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $products = Product::excludeDeleted()->allowedForUser($user);

            // Filter product by name pattern
            OrbitInput::get('keyword', function ($name) use ($products) {
                $products->where('products.product_name', 'like', "%$name%");
            });

            $_products = clone $products;

            // Get the take args
            $take = $maxRecord;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;
            });
            $products->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $products) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $products->skip($skip);

            // Default sort by
            $sortBy = 'products.product_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'product_name'              => 'products.product_name',
                    'product_price'             => 'products.price',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $products->orderBy($sortBy, $sortMode);

            $totalRec = $_products->count();
            $listOfRec = $products->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            // $products = Product::excludeDeleted()->where('product_name', 'LIKE', "%$keyword%")->orWhere('product_code', 'LIKE', "%$keyword%")->orWhere('short_description', 'LIKE', "%$keyword%")->orWhere('long_description', 'LIKE', "%$keyword%")->get();
            $retailer = $this->getRetailerInfo();
            return View::make('mobile-ci.search', array('page_title'=>Lang::get('mobileci.page_title.searching'), 'retailer' => $retailer, 'data' => $data));
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

    public function getProductView()
    {
        try {
            $product_id = trim(OrbitInput::get('id'));
            $product = Product::excludeDeleted()->where('product_id', $product_id)->first();
            $retailer = $this->getRetailerInfo();
            return View::make('mobile-ci.product', array('page_title' => strtoupper($product->product_name), 'retailer' => $retailer, 'product' => $product));
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

    public function getCartView()
    {
        try {
            $retailer = $this->getRetailerInfo();
            return View::make('mobile-ci.cart', array('page_title'=>Lang::get('mobileci.page_title.cart'), 'retailer'=>$retailer));
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

    public function getTransferCartView()
    {
        try {
            $retailer = $this->getRetailerInfo();
            return View::make('mobile-ci.transfer-cart', array('page_title'=>Lang::get('mobileci.page_title.transfercart'), 'retailer'=>$retailer));
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

    public function getPaymentView()
    {
        try {
            $retailer = $this->getRetailerInfo();
            return View::make('mobile-ci.payment', array('page_title'=>Lang::get('mobileci.page_title.payment'), 'retailer'=>$retailer));
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

    public function getThankYouView()
    {
        try {
            $retailer = $this->getRetailerInfo();
            return View::make('mobile-ci.thankyou', array('retailer'=>$retailer));
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

    public function getWelcomeView()
    {
        try {
            $retailer = $this->getRetailerInfo();
            $user = $this->api->user;
            return View::make('mobile-ci.welcome', array('retailer'=>$retailer, 'user'=>$user));
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

    public function getRetailerInfo()
    {
        try {
            $this->checkAuth();
            $user = $this->api->user;
            if (! ACL::create($user)->isAllowed('view_retailer')) {
                $errorlang = Lang::get('validation.orbit.actionlist.view_retailer');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorlang));
                ACL::throwAccessForbidden($message);
            }
            
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

            \App::instance('orbit.validation.user', $user);

            return TRUE;
        });
    }
}