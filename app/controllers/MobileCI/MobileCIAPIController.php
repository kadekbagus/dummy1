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
use \Category;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Cart;
use \CartDetail;
use \Exception;

class MobileCIAPIController extends ControllerAPI
{
    protected $session = NULL;

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
                        ->excludeDeleted()
                        ->where('user_email', $email)
                        ->whereHas('role', function($query)
                            {
                                $query->where('role_name','Consumer');
                            })
                        ->first();

            if (! is_object($user)) {
                // $message = \Lang::get('validation.orbit.access.loginfailed');
                // ACL::throwAccessForbidden($message);
                $response = \LoginAPIController::create('raw')->postRegisterUserInShop();
                if ($response->code === 0)
                {
                    $user = $response->data;
                }

                // return $this->render($response);
            } 

            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
            );
            $config = new SessionConfig(Config::get('orbit.session'));
            $session = new Session($config);
            $session->enableForceNew()->start($data);

            $retailer = $this->getRetailerInfo();
            $cart = Cart::where('status', 'active')->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->first();
            if(is_null($cart)){
                $cart = new Cart;
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

    public function getActivationView()
    {
        // $_POST['token'] = OrbitInput::get('token');
        // $response = \LoginAPIController::create('raw')->postRegisterTokenCheck();
        // if ($response->code === 0)
        // {
        //     $user = $response->data;
        //     $user->setHidden(array('user_password', 'apikey'));
        //     Auth::login($user);
        // }

        // return $this->render($response);
        try {
            $retailer = $this->getRetailerInfo();
            return View::make('mobile-ci.activation', array('retailer'=>$retailer));
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

    // public function postRegisterUserInShop()
    // {
        
            // $httpCode = 200;

            // $this->registerCustomValidation();

            // $email = OrbitInput::post('email');

            // $validator = \Validator::make(
            //     array(
            //         'email'     => $email,
            //     ),
            //     array(
            //         'email'     => 'required|email|orbit.email.exists',
            //     )
            // );

            // // Run the validation
            // if ($validator->fails()) {
            //     $errorMessage = $validator->messages()->first();
            //     OrbitShopAPI::throwInvalidArgument($errorMessage);
            // }

            // // Begin database transaction
            // $this->beginTransaction();

            // $newuser = new User();
            // $newuser->username = $email;
            // $newuser->user_password = str_random(8);
            // $newuser->user_email = $email;
            // $newuser->status = 'active';
            // $newuser->user_role_id = Role::where('role_name','Consumer')->first()->role_id;
            // $newuser->user_ip = $_SERVER['REMOTE_ADDR'];

            // $newuser->save();

            // $userdetail = new UserDetail();
            // $userdetail = $newuser->userdetail()->save($userdetail);

            // $newuser->setRelation('userdetail', $userdetail);
            // $newuser->userdetail = $userdetail;

            // // token
            // $token = new Token();
            // $token->token_name = 'user_registration_mobile';
            // $token->token_value = $token->generateToken($email);
            // $token->status = 'active';
            // $token->email = $email;
            // $token->expire = date('Y-m-d H:i:s', strtotime('+14 days'));
            // $token->ip_address = $_SERVER['REMOTE_ADDR'];
            // $token->user_id = $newuser->user_id;
            // $token->save();

            // $apikey = new Apikey();
            // $apikey->api_key = Apikey::genApiKey($newuser);
            // $apikey->api_secret_key = Apikey::genSecretKey($newuser);
            // $apikey->status = 'active';
            // $apikey->user_id = $newuser->user_id;
            // $apikey = $newuser->apikey()->save($apikey);

            // // send the email
            // \Mail::send('emails.registration.activation-html', array('token' => $token->token_value, 'email' => $email), function($message)
            // {
            //     $email = OrbitInput::post('email');
            //     $message->from('registration@dominopos.com', 'Orbit Registration')->subject('You are almost in Orbit!');
            //     $message->to($email);
            // });

            // // authenticate user
            // \Auth::login($newuser);

            // $this->response->data = $newuser;

            // // Commit the changes
            // $this->commit();

        
    // }

    public function getHomeView()
    {
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $new_products = Product::with('media')->where('new_from','<=', Carbon::now())->where('new_until', '>=', Carbon::now())->get();

            $cartdata = $this->getCartForToolbar();

            return View::make('mobile-ci.home', array('page_title'=>Lang::get('mobileci.page_title.home'), 'retailer' => $retailer, 'new_products' => $new_products, 'cartdata' => $cartdata));
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getSignInView()
    {
        try {
            $user = $this->getLoggedInUser();
            
            return \Redirect::to('/customer/welcome');
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            if($e->getMessage() === 'Session error: user not found.' || $e->getMessage() === 'Invalid session data.') {
                $retailer = $this->getRetailerInfo();
                return View::make('mobile-ci.signin', array('retailer'=>$retailer));
            }
        }
    }

    // public function getSignUpView()
    // {
    //     try {
    //         $retailer = $this->getRetailerInfo();
    //         return View::make('mobile-ci.signup', array('email'=>'', 'retailer'=>$retailer));
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
    // }

    public function getCatalogueView()
    {
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();
            $families = Category::has('product1')->where('merchant_id', $retailer->parent_id)->excludeDeleted()->get();

            $cartdata = $this->getCartForToolbar();

            return View::make('mobile-ci.catalogue', array('page_title'=>Lang::get('mobileci.page_title.catalogue'), 'retailer' => $retailer, 'families' => $families, 'cartdata' => $cartdata));
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getSearchProduct()
    {
        try {
            // Require authentication
            $user = $this->getLoggedInUser();

            $sort_by = OrbitInput::get('sort_by');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:product_name,price',
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

            $retailer = $this->getRetailerInfo();

            $products = Product::whereHas('retailers', function($query) use ($retailer) {
                            $query->where('retailer_id', $retailer->merchant_id);
                        })->where('merchant_id', $retailer->parent_id)->excludeDeleted();

            // Filter product by name pattern
            OrbitInput::get('keyword', function ($name) use ($products) {
                $products->where(function($q) use ($name) {
                    $q  ->where('products.product_name', 'like', "%$name%")
                        ->orWhere('products.upc_code', 'like', "%$name%")
                        ->orWhere('products.short_description', 'like', "%$name%")
                        ->orWhere('products.long_description', 'like', "%$name%")
                        ->orWhere('products.short_description', 'like', "%$name%");
                });
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

            OrbitInput::get('sort_by', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'product_name'      => 'products.product_name',
                    'price'             => 'products.price',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sort_mode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }else{
                    $sortMode = 'desc';
                }
            });
            $products->orderBy($sortBy, $sortMode);

            $totalRec = $_products->count();
            $listOfRec = $products->get();

            $search_limit = Config::get('orbit.shop.search_limit');
            if($totalRec>$search_limit){
                $data = new stdclass();
                $data->status = 0;
            }else{
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = count($listOfRec);
                $data->records = $listOfRec;
            }

            $cartdata = $this->getCartForToolbar();

            return View::make('mobile-ci.search', array('page_title'=>Lang::get('mobileci.page_title.searching'), 'retailer' => $retailer, 'data' => $data, 'cartdata' => $cartdata));
            
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getProductList()
    {
        try {
            $user = $this->getLoggedInUser();

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sort_by');
            $family_id = OrbitInput::get('family_id');
            $family_level = OrbitInput::get('family_level');
            $families = OrbitInput::get('families');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'family_id' => $family_id,
                ),
                array(
                    'sort_by' => 'in:product_name,price',
                    'family_id' => 'orbit.exists.category',
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

            $retailer = $this->getRetailerInfo();
            $nextfamily = $family_level + 1;

            $subfamilies = Category::excludeDeleted();
            if($nextfamily < 6) {
                $subfamilies = Category::where('merchant_id', $retailer->parent_id)->whereHas('product'.$nextfamily, function($q) use ($family_id, $family_level, $families) {
                    $nextfamily = $family_level + 1;
                    for($i = 1; $i < count($families); $i++) {
                        $q->where('products.category_id'.$i, $families[$i-1]);
                    }

                    $q  ->where('products.category_id'.$family_level, $family_id)
                        ->where('products.category_id'.$nextfamily, '<>', 'NULL')
                        ->where('products.status', 'active');
                })->get();
            } else {
                $subfamilies = NULL;
            }

            

            $products = Product::whereHas('retailers', function($query) use ($retailer) {
                $query->where('retailer_id', $retailer->merchant_id);
            })->where('merchant_id', $retailer->parent_id)->excludeDeleted()->where(function($q) use ($family_level, $family_id, $families) {
                for($i = 1; $i < count($families); $i++) {
                    $q->where('category_id'.$i, $families[$i-1]);
                }
                $q->where('category_id' . $family_level, $family_id);
                for($i = $family_level + 1; $i <= 5; $i++) {
                    $q->where('category_id' . $i, NULL);
                }
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

            OrbitInput::get('sort_by', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'product_name'      => 'products.product_name',
                    'price'             => 'products.price',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sort_mode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }else{
                    $sortMode = 'desc';
                }
            });
            $products->orderBy($sortBy, $sortMode);

            $totalRec = $_products->count();
            $listOfRec = $products->get();

            $search_limit = Config::get('orbit.shop.search_limit');
            if($totalRec>$search_limit){
                $data = new stdclass();
                $data->status = 0;
            }else{
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = count($listOfRec);
                $data->records = $listOfRec;
            }

            $cartdata = $this->getCartForToolbar();
            return View::make('mobile-ci.product-list', array('retailer' => $retailer, 'data' => $data, 'subfamilies' => $subfamilies, 'cartdata' => $cartdata));
            
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
        
    }

    public function getProductView()
    {
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();
            $product_id = trim(OrbitInput::get('id'));
            $product = Product::whereHas('retailers', function($query) use ($retailer) {
                            $query->where('retailer_id', $retailer->merchant_id);
                        })->excludeDeleted()->where('product_id', $product_id)->first();
            $cartdata = $this->getCartForToolbar();

            if(is_null($product)){
                return View::make('mobile-ci.404', array('page_title' => "Error 404", 'retailer' => $retailer, 'cartdata' => $cartdata));
            } else {
                return View::make('mobile-ci.product', array('page_title' => strtoupper($product->product_name), 'retailer' => $retailer, 'product' => $product, 'cartdata' => $cartdata));
            }
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getCartView()
    {
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            // $cart = Cart::where('status', 'active')->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->first();
            
            $cartdata = $this->getCartForToolbar();

            return View::make('mobile-ci.cart', array('page_title'=>Lang::get('mobileci.page_title.cart'), 'retailer'=>$retailer, 'cartdata' => $cartdata));
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getTransferCartView()
    {
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            return View::make('mobile-ci.transfer-cart', array('page_title'=>Lang::get('mobileci.page_title.transfercart'), 'retailer'=>$retailer));
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getPaymentView()
    {
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            return View::make('mobile-ci.payment', array('page_title'=>Lang::get('mobileci.page_title.payment'), 'retailer'=>$retailer));
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getThankYouView()
    {
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            return View::make('mobile-ci.thankyou', array('retailer'=>$retailer));
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getWelcomeView()
    {
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();
            $cartdata = $this->getCartForToolbar();

            return View::make('mobile-ci.welcome', array('retailer'=>$retailer, 'user'=>$user, 'cartdata' => $cartdata));
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getRetailerInfo()
    {
        try {
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

    public function postAddToCart()
    {
        try {
            $this->registerCustomValidation();

            $retailer = $this->getRetailerInfo();

            $user = $this->getLoggedInUser();

            $product_id = OrbitInput::post('productid');
            $quantity = OrbitInput::post('qty');

            $validator = \Validator::make(
                array(
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                ),
                array(
                    'product_id' => 'required|orbit.exists.product',
                    'quantity' => 'required|numeric',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            
            $this->beginTransaction();

            $cart = Cart::where('status', 'active')->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->first();
            if(empty($cart)){
                $cart = new Cart;
                $cart->customer_id = $user->user_id;
                $cart->merchant_id = $retailer->parent_id;
                $cart->retailer_id = $retailer->merchant_id;
                $cart->status = 'active';
                $cart->save();
                $cart->cart_code = Cart::CART_INCREMENT + $cart->cart_id;
                $cart->save();
            }
            
            $product = Product::with('tax1', 'tax2')->where('product_id', $product_id)->first();

            $cart->total_item = $cart->total_item + 1;
            // $cart->subtotal = $cart->subtotal + $product->price;
            
            // $tax_value1 = $product->tax1->tax_value;
            // if(empty($tax_value1)) {
            //     $tax1 = 0;
            // } else {
            //     $tax1 = $product->tax1->tax_value * $product->price;
            // }

            // $tax_value2 = $product->tax2->tax_value;
            // if(empty($tax_value2)) {
            //     $tax2 = 0;
            // } else {
            //     $tax2 = $product->tax2->tax_value * $product->price;
            // }
            
            // $cart->vat = $cart->vat + $tax1 + $tax2;
            // $cart->total_to_pay = $cart->subtotal + $cart->vat;
            $cart->save();

            $cartdetail = CartDetail::excludeDeleted()->where('product_id', $product_id)->where('cart_id', $cart->cart_id)->first();
            if(empty($cartdetail)){
                $cartdetail = new CartDetail;
                $cartdetail->cart_id = $cart->cart_id;
                $cartdetail->product_id = $product->product_id;
                $cartdetail->product_variant_id = $product->product_id;
                $cartdetail->quantity = $quantity;
                $cartdetail->status = 'active';
                $cartdetail->save();
            } else {
                $cartdetail->quantity = $cartdetail->quantity + 1;
                $cartdetail->save();
            }
            
            $this->response->message = 'success';
            $this->response->data = $cartdetail;

            $this->commit();

        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
        
        return $this->render();
    }

    public function postUpdateCart()
    {
        try {
            $this->registerCustomValidation();

            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartdetailid = OrbitInput::post('detail');
            $quantity = OrbitInput::post('qty');

            $validator = \Validator::make(
                array(
                    'cartdetailid' => $cartdetailid,
                    'quantity' => $quantity,
                ),
                array(
                    'cartdetailid' => 'required|orbit.exists.cartdetailid',
                    'quantity' => 'required|numeric',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            
            $this->beginTransaction();
            
            $cartdetail = CartDetail::where('cart_detail_id', $cartdetailid)->first();
            $cart = Cart::where('cart_id', $cartdetail->cart_id)->excludeDeleted()->first();

            $currentqty = $cartdetail->quantity;
            $deltaqty = $quantity - $currentqty;

            $cartdetail->quantity = $quantity;

            $cart->total_item = $cart->total_item + $deltaqty;
            $cart->subtotal = $cart->subtotal + ($deltaqty * $cartdetail->price);
            
            $product = Product::with('tax1', 'tax2')->where('product_id', $cartdetail->product_id)->first();

            $tax_value1 = $product->tax1->tax_value;
            if(empty($tax_value1)) {
                $tax1 = 0;
            } else {
                $tax1 = $product->tax1->tax_value * $product->price;
            }

            $tax_value2 = $product->tax2->tax_value;
            if(empty($tax_value2)) {
                $tax2 = 0;
            } else {
                $tax2 = $product->tax2->tax_value * $product->price;
            }
            
            $cart->vat = $cart->vat + ($deltaqty * ($tax1 + $tax2));
            $cart->total_to_pay = $cart->subtotal + $cart->vat;
            $cart->save();

            $cartdetail = CartDetail::excludeDeleted()->where('product_id', $product->product_id)->where('cart_id', $cart->cart_id)->first();
            if(empty($cartdetail)){
                $cartdetail = new CartDetail;
                $cartdetail->cart_id = $cart->cart_id;
                $cartdetail->product_id = $product->product_id;
                $cartdetail->price = $product->price;
                $cartdetail->upc = $product->upc_code;
                $cartdetail->sku = $product->product_code;
                $cartdetail->quantity = $quantity;
                $cartdetail->status = 'active';
                $cartdetail->save();
            } else {
                $cartdetail->quantity = $cartdetail->quantity + 1;
                $cartdetail->save();
            }
            
            $cartdata = new stdclass();
            $cartdata->cart = $cart;
            $cartdata->cartdetail = $cartdetail;
            $this->response->message = 'success';
            $this->response->data = $cartdata;

            $this->commit();
            return $this->render();

        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            $this->rollback();
            return $e;
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

        // Check category, it should exists
        Validator::extend('orbit.exists.category', function ($attribute, $value, $parameters) {
            $category = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();

            if (empty($category)) {
                return FALSE;
            }

            \App::instance('orbit.validation.category', $category);

            return TRUE;
        });

        // Check product, it should exists
        Validator::extend('orbit.exists.product', function ($attribute, $value, $parameters) {
            $product = Product::excludeDeleted()
                        ->where('product_id', $value)
                        ->first();

            if (empty($product)) {
                return FALSE;
            }

            \App::instance('orbit.validation.product', $product);

            return TRUE;
        });

        // Check cart, it should exists
        Validator::extend('orbit.exists.cartdetailid', function ($attribute, $value, $parameters) {
            $retailer = $this->getRetailerInfo();

            $user = $this->getLoggedInUser();

            $cartdetail = CartDetail::whereHas('cart', function($q) use ($user, $retailer)
            {
                $q->where('carts.customer_id', $user->user_id)->where('carts.retailer_id', $retailer->merchant_id);
            })->excludeDeleted()
                        ->where('cart_detail_id', $value)
                        ->first();

            if (empty($cartdetail)) {
                return FALSE;
            }

            \App::instance('orbit.validation.cartdetailid', $cartdetail);

            return TRUE;
        });
    }

    public function redirectIfNotLoggedIn($e)
    {
        if($e->getMessage() === 'Session error: user not found.' || $e->getMessage() === 'Invalid session data.') {
            return \Redirect::to('/customer');
        } else {
            return \Redirect::to('/customer/welcome');
        }
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

    protected function getCartForToolbar()
    {
        try{
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();
            $cart = Cart::where('status', 'active')->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->first();
            if(is_null($cart)){
                $cart = new Cart;
                $cart->customer_id = $user->user_id;
                $cart->merchant_id = $retailer->parent_id;
                $cart->retailer_id = $retailer->merchant_id;
                $cart->status = 'active';
                $cart->save();
                $cart->cart_code = Cart::CART_INCREMENT + $cart->cart_id;
                $cart->save();
            }

            $cartdetails = CartDetail::with('product')->where('status', 'active')->where('cart_id', $cart->cart_id)->get();
            $cartdata = new stdclass();
            $cartdata->cart = $cart;
            $cartdata->cartdetails = $cartdetails;
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
        return $cartdata;
    }
}
