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
use \Promotion;
use \Coupon;
use Carbon\Carbon as Carbon;
use \stdclass;
use \Category;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Cart;
use \CartDetail;
use \Exception;
use \DB;

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

    public function getHomeView()
    {
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();

            $new_products = Product::with('media')->where('new_from','<=', Carbon::now())->where('new_until', '>=', Carbon::now())->get();
            
            $promotion = Promotion::excludeDeleted()->where('is_coupon', 'N')->where('merchant_id', $retailer->parent_id)->whereHas('retailers', function($q) use ($retailer)
                {
                    $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                })
                ->where(function($q) 
                {
                    $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(function($qr)
                    {
                        $qr->where('begin_date', '<=', Carbon::now())->where('is_permanent', '=', 'Y');
                    });
                })
                ->orderBy(DB::raw('RAND()'))->first();

            $promo_products = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND (p.promotion_type = "product" OR p.promotion_type = "cart") and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                left join ' . DB::getTablePrefix() . 'products prod on 
                (
                    (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.discount_object_type="family") AND 
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id));

            // $cartdata = $this->getCartForToolbar();

            $cartitems = $this->getCartForToolbar();

            return View::make('mobile-ci.home', array('page_title'=>Lang::get('mobileci.page_title.home'), 'retailer' => $retailer, 'new_products' => $new_products, 'promo_products' => $promo_products, 'promotion' => $promotion, 'cartitems' => $cartitems));
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            return $e->getMessage();
        }
    }

    public function getSignInView()
    {
        try {
            $user = $this->getLoggedInUser();
            
            return \Redirect::to('/customer/welcome');
        } catch (Exception $e) {
            $retailer = $this->getRetailerInfo();
            if($e->getMessage() === 'Session error: user not found.' || $e->getMessage() === 'Invalid session data.' || $e->getMessage() === 'IP address miss match.' || $e->getMessage() === 'User agent miss match.') {
                return View::make('mobile-ci.signin', array('retailer'=>$retailer));
            } else {
                return View::make('mobile-ci.signin', array('retailer'=>$retailer));
            }
        }
    }

    public function getCatalogueView()
    {
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();
            $families = Category::has('product1')->where('merchant_id', $retailer->parent_id)->excludeDeleted()->get();

            $cartitems = $this->getCartForToolbar();

            return View::make('mobile-ci.catalogue', array('page_title'=>Lang::get('mobileci.page_title.catalogue'), 'retailer' => $retailer, 'families' => $families, 'cartitems' => $cartitems));
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
                $maxRecord = 100;
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

            $cartitems = $this->getCartForToolbar();

            $promotions = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                inner join ' . DB::getTablePrefix() . 'products prod on 
                (
                    (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.discount_object_type="family") AND 
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id));
            
            $couponstocatchs = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                inner join ' . DB::getTablePrefix() . 'products prod on 
                (
                    (pr.rule_object_type="product" AND pr.rule_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.rule_object_type="family") AND 
                        ((pr.rule_object_id1 IS NULL) OR (pr.rule_object_id1=prod.category_id1)) AND 
                        ((pr.rule_object_id2 IS NULL) OR (pr.rule_object_id2=prod.category_id2)) AND
                        ((pr.rule_object_id3 IS NULL) OR (pr.rule_object_id3=prod.category_id3)) AND
                        ((pr.rule_object_id4 IS NULL) OR (pr.rule_object_id4=prod.category_id4)) AND
                        ((pr.rule_object_id5 IS NULL) OR (pr.rule_object_id5=prod.category_id5))
                    )
                )
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id));
            
            $coupons = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                inner join ' . DB::getTablePrefix() . 'products prod on
                (
                    (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.discount_object_type="family") AND 
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id));

            $product_on_promo = array();
            foreach($promotions as $promotion) {
                $product_on_promo[] = $promotion->product_id;
            }

            $totalRec = $_products->count();
            $listOfRec = $products->get();

            foreach($listOfRec as $product) {
                $prices = array();
                foreach($product->variants as $variant) {
                    $prices[] = $variant->price;
                }

                // set minimum price
                $min_price = min($prices);
                $product->min_price = $min_price + 0;

                // set on_promo flag
                $promo_for_this_product = array_filter($promotions, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if(count($promo_for_this_product) > 0) {
                    $discount=0;
                    foreach($promo_for_this_product as $promotion) {
                        if($promotion->rule_type == 'product_discount_by_percentage') {
                            $discount = $discount + (min($prices) * $promotion->discount_value);
                        } elseif($promotion->rule_type == 'product_discount_by_value') {
                            $discount = $discount + $promotion->discount_value;
                        }
                    }
                    $product->on_promo = true;
                    $product->priceafterpromo = $min_price - $discount;
                } else {
                    $product->on_promo = false;
                }

                // set coupons to catch flag
                $couponstocatch_this_product = array_filter($couponstocatchs, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if(count($couponstocatch_this_product) > 0) {
                    $product->on_couponstocatch = true;
                } else {
                    $product->on_couponstocatch = false;
                }

                // set coupons flag
                $coupon_for_this_product = array_filter($coupons, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if(count($coupon_for_this_product) > 0) {
                    $product->on_coupons = true;
                } else {
                    $product->on_coupons = false;
                }

                // set is_new flag
                if($product->new_from <= \Carbon\Carbon::now() && $product->new_until >= \Carbon\Carbon::now()) {
                    $product->is_new = true;
                } else {
                    $product->is_new = false;
                }
            }

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

            return View::make('mobile-ci.search', array('page_title'=>Lang::get('mobileci.page_title.searching'), 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo));
            
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

            $products = Product::with('variants')->whereHas('retailers', function($query) use ($retailer) {
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

            $promotions = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                inner join ' . DB::getTablePrefix() . 'products prod on 
                (
                    (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.discount_object_type="family") AND 
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id));
            
            $couponstocatchs = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                inner join ' . DB::getTablePrefix() . 'products prod on 
                (
                    (pr.rule_object_type="product" AND pr.rule_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.rule_object_type="family") AND 
                        ((pr.rule_object_id1 IS NULL) OR (pr.rule_object_id1=prod.category_id1)) AND 
                        ((pr.rule_object_id2 IS NULL) OR (pr.rule_object_id2=prod.category_id2)) AND
                        ((pr.rule_object_id3 IS NULL) OR (pr.rule_object_id3=prod.category_id3)) AND
                        ((pr.rule_object_id4 IS NULL) OR (pr.rule_object_id4=prod.category_id4)) AND
                        ((pr.rule_object_id5 IS NULL) OR (pr.rule_object_id5=prod.category_id5))
                    )
                )
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id));

            $coupons = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                inner join ' . DB::getTablePrefix() . 'products prod on
                (
                    (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.discount_object_type="family") AND 
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id));

            $product_on_promo = array();
            foreach($promotions as $promotion) {
                $product_on_promo[] = $promotion->product_id;
            }

            foreach($listOfRec as $product) {
                $prices = array();
                foreach($product->variants as $variant) {
                    $prices[] = $variant->price;
                }

                // set minimum price
                $min_price = min($prices);
                $product->min_price = $min_price + 0;

                // set on_promo flag
                $promo_for_this_product = array_filter($promotions, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if(count($promo_for_this_product) > 0) {
                    $discount=0;
                    foreach($promo_for_this_product as $promotion) {
                        if($promotion->rule_type == 'product_discount_by_percentage') {
                            $discount = $discount + (min($prices) * $promotion->discount_value);
                        } elseif($promotion->rule_type == 'product_discount_by_value') {
                            $discount = $discount + $promotion->discount_value;
                        }
                    }
                    $product->on_promo = true;
                    $product->priceafterpromo = $min_price - $discount;
                } else {
                    $product->on_promo = false;
                }

                // set coupons to catch flag
                $couponstocatch_this_product = array_filter($couponstocatchs, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if(count($couponstocatch_this_product) > 0) {
                    $product->on_couponstocatch = true;
                } else {
                    $product->on_couponstocatch = false;
                }

                // set coupons flag
                $coupon_for_this_product = array_filter($coupons, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if(count($coupon_for_this_product) > 0) {
                    $product->on_coupons = true;
                } else {
                    $product->on_coupons = false;
                }

                // set is_new flag
                if($product->new_from <= \Carbon\Carbon::now() && $product->new_until >= \Carbon\Carbon::now()) {
                    $product->is_new = true;
                } else {
                    $product->is_new = false;
                }
            }

            // $listOfRec = $products;
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

            $cartitems = $this->getCartForToolbar();
            return View::make('mobile-ci.product-list', array('retailer' => $retailer, 'data' => $data, 'subfamilies' => $subfamilies, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo, 'couponstocatchs' => $couponstocatchs));
            
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            // if($e->getMessage() === 'Invalid session data.'){
                return $e->getMessage();
            // }
        }
        
    }

    public function getProductView()
    {
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();
            $product_id = trim(OrbitInput::get('id'));
            $product = Product::with('variants', 'attribute1', 'attribute2', 'attribute3', 'attribute4', 'attribute5')->whereHas('retailers', function($query) use ($retailer) {
                            $query->where('retailer_id', $retailer->merchant_id);
                        })->excludeDeleted()->where('product_id', $product_id)->first();

            $promo_products = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N" AND p.merchant_id = :merchantid
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id AND prr.retailer_id = :retailerid
                inner join ' . DB::getTablePrefix() . 'products prod on 
                (
                    (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.discount_object_type="family") AND 
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )
                WHERE prod.product_id = :productid'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product->product_id));
            
            $couponstocatchs = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                inner join ' . DB::getTablePrefix() . 'products prod on 
                (
                    (pr.rule_object_type="product" AND pr.rule_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.rule_object_type="family") AND 
                        ((pr.rule_object_id1 IS NULL) OR (pr.rule_object_id1=prod.category_id1)) AND 
                        ((pr.rule_object_id2 IS NULL) OR (pr.rule_object_id2=prod.category_id2)) AND
                        ((pr.rule_object_id3 IS NULL) OR (pr.rule_object_id3=prod.category_id3)) AND
                        ((pr.rule_object_id4 IS NULL) OR (pr.rule_object_id4=prod.category_id4)) AND
                        ((pr.rule_object_id5 IS NULL) OR (pr.rule_object_id5=prod.category_id5))
                    )
                )
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND prod.product_id = :productid'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product->product_id));
            
            $coupons = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                inner join ' . DB::getTablePrefix() . 'products prod on
                (
                    (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.discount_object_type="family") AND 
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND prod.product_id = :productid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $product->product_id));

            $attributes = DB::select(DB::raw('SELECT v.upc, v.sku, v.product_variant_id, av1.value as value1, av1.product_attribute_value_id as attr_val_id1, av2.product_attribute_value_id as attr_val_id2, av3.product_attribute_value_id as attr_val_id3, av4.product_attribute_value_id as attr_val_id4, av5.product_attribute_value_id as attr_val_id5, av2.value as value2, av3.value as value3, av4.value as value4, av5.value as value5, v.price, pa1.product_attribute_name as attr1, pa2.product_attribute_name as attr2, pa3.product_attribute_name as attr3, pa4.product_attribute_name as attr4, pa5.product_attribute_name as attr5 FROM ' . DB::getTablePrefix() . 'product_variants v
                inner join ' . DB::getTablePrefix() . 'products p on p.product_id = v.product_id 
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av1 on av1.product_attribute_value_id = v.product_attribute_value_id1 
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av2 on av2.product_attribute_value_id = v.product_attribute_value_id2
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av3 on av3.product_attribute_value_id = v.product_attribute_value_id3
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av4 on av4.product_attribute_value_id = v.product_attribute_value_id4
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av5 on av5.product_attribute_value_id = v.product_attribute_value_id5
                left join ' . DB::getTablePrefix() . 'product_attributes as pa1 on pa1.product_attribute_id = av1.product_attribute_id
                left join ' . DB::getTablePrefix() . 'product_attributes as pa2 on pa2.product_attribute_id = av2.product_attribute_id
                left join ' . DB::getTablePrefix() . 'product_attributes as pa3 on pa3.product_attribute_id = av3.product_attribute_id
                left join ' . DB::getTablePrefix() . 'product_attributes as pa4 on pa4.product_attribute_id = av4.product_attribute_id
                left join ' . DB::getTablePrefix() . 'product_attributes as pa5 on pa5.product_attribute_id = av5.product_attribute_id 
                WHERE p.product_id = :productid'), array('productid' => $product->product_id));

            $cartitems = $this->getCartForToolbar();

            if(is_null($product)){
                return View::make('mobile-ci.404', array('page_title' => "Error 404", 'retailer' => $retailer, 'cartdata' => $cartdata));
            } else {
                return View::make('mobile-ci.product', array('page_title' => strtoupper($product->product_name), 'retailer' => $retailer, 'product' => $product, 'cartitems' => $cartitems, 'promotions' => $promo_products, 'attributes' => $attributes, 'couponstocatchs' => $couponstocatchs, 'coupons' => $coupons));
            }
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
            // return $e->getMessage();
        }
    }

    public function postCartProductPopup()
    {
        try {
            $this->registerCustomValidation();
            $product_id = OrbitInput::post('detail');

            $validator = \Validator::make(
                array(
                    'product_id' => $product_id,
                ),
                array(
                    'product_id' => 'required|orbit.exists.product',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $product = Product::excludeDeleted()->where('product_id', $product_id)->first();

            $this->response->message = 'success';
            $this->response->data = $product;

            return $this->render();
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
            // return $e->getMessage();
        }
    }

    public function postCartPromoPopup()
    {
        try {
            $this->registerCustomValidation();
            $promotion_id = OrbitInput::post('promotion_detail');

            $validator = \Validator::make(
                array(
                    'promotion_id' => $promotion_id,
                ),
                array(
                    'promotion_id' => 'required|orbit.exists.promotion',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $promotion = Promotion::excludeDeleted()->where('promotion_id', $promotion_id)->first();

            $this->response->message = 'success';
            $this->response->data = $promotion;

            return $this->render();
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
            // return $e;
        }
    }

    public function postCartCouponPopup()
    {
        try {
            $this->registerCustomValidation();
            $promotion_id = OrbitInput::post('promotion_detail');

            $validator = \Validator::make(
                array(
                    'promotion_id' => $promotion_id, 
                ),
                array(
                    'promotion_id' => 'required|orbit.exists.coupon',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $promotion = Coupon::excludeDeleted()->where('promotion_id', $promotion_id)->first();

            $this->response->message = 'success';
            $this->response->data = $promotion;

            return $this->render();
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
            // return $e;
        }
    }

    public function getCartView()
    {
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();
            
            $cartitems = $this->getCartForToolbar();

            $cartdata = $this->getCartData();

            $cartsummary = new stdclass();

            $subtotal = 0;
            $vat = 0;
            $total = 0;
            $total_discount = 0;

            $promo_products = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N" AND p.merchant_id = :merchantid
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id AND prr.retailer_id = :retailerid
                inner join ' . DB::getTablePrefix() . 'products prod on 
                (
                    (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.discount_object_type="family") AND 
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id));

            // check for cart based promotions
            $promo_carts = Promotion::with('promotionrule')->excludeDeleted()->where('is_coupon', 'N')->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('retailers', function($q) use ($retailer)
                {
                    $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                })
                ->where(function($q) 
                {
                    $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(function($qr)
                    {
                        $qr->where('begin_date', '<=', Carbon::now())->where('is_permanent', '=', 'Y');
                    });
                })
                ->get();

            foreach ($cartdata->cartdetails as $cartdetail) {
                $variant = \ProductVariant::where('product_variant_id', $cartdetail->product_variant_id)->excludeDeleted()->first();
                $product = Product::with('tax1', 'tax2')->where('product_id', $variant->product_id)->excludeDeleted()->first();
                
                $filtered = array_filter($promo_products, function($v) use ($product) { return $v->product_id == $product->product_id; });

                $discount = 0;
                foreach($filtered as $promotion){
                    if($promotion->product_id == $product->product_id) {
                        if($promotion->rule_type == 'product_discount_by_percentage') {
                            $discount = $discount +  ( $variant->price * $promotion->discount_value);
                        } elseif ($promotion->rule_type == 'product_discount_by_value') {
                            $discount = $discount + $promotion->discount_value;
                        }
                    }
                }
                
                $subtotal = $subtotal + (($variant->price - $discount) * $cartdetail->quantity);
                $priceaftertax = ($variant->price - $discount) * $cartdetail->quantity;
                if(!is_null($product->tax1)) {
                    $vat1 = $product->tax1->tax_value * ($variant->price - $discount) * $cartdetail->quantity;
                    $vat = $vat + $vat1;
                    $priceaftertax = $priceaftertax + $vat1;
                }
                if(!is_null($product->tax2)) {
                    $vat2 = $product->tax2->tax_value * ($variant->price - $discount) * $cartdetail->quantity;
                    $vat = $vat + $vat2;
                    $priceaftertax = $priceaftertax + $vat2;
                }

                $total_discount = $total_discount + ($discount * $cartdetail->quantity);

                $attributes = array();
                if($cartdetail->attributeValue1['value']){
                    $attributes[] = $cartdetail->attributeValue1['value'];
                }
                if($cartdetail->attributeValue2['value']){
                    $attributes[] = $cartdetail->attributeValue2['value'];
                }
                if($cartdetail->attributeValue3['value']){
                    $attributes[] = $cartdetail->attributeValue3['value'];
                }
                if($cartdetail->attributeValue4['value']){
                    $attributes[] = $cartdetail->attributeValue4['value'];
                }
                if($cartdetail->attributeValue5['value']){
                    $attributes[] = $cartdetail->attributeValue5['value'];
                }

                $cartdetail->promoforthisproducts = $filtered;
                $cartdetail->attributes = $attributes;
                $cartdetail->priceafterpromo = $variant->price - $discount;
                $cartdetail->ammountbeforepromo = $variant->price * $cartdetail->quantity;
                $cartdetail->ammountafterpromo = ($variant->price - $discount) * $cartdetail->quantity;
                $cartdetail->ammountaftertax = $priceaftertax;
            }

            // check for available cart based coupons
            $coupon_carts = Coupon::with('couponrule')->excludeDeleted()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function($q) use ($retailer)
            {
                $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
            })
            ->whereHas('issuedcoupons', function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now());
            })
            ->where(function($q) 
            {
                $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(function($qr)
                {
                    $qr->where('begin_date', '<=', Carbon::now())->where('is_permanent', '=', 'Y');
                });
            })
            ->get();

            $cartdiscounts = 0;
            $subtotalaftercartpromo = $subtotal;
            $acquired_promo_carts = array();
            foreach($promo_carts as $promo_cart){
                // dd($promo_cart->promotionrule->rule_value);
                if($subtotal >= $promo_cart->promotionrule->rule_value){
                    if($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') {
                        $discount = $subtotal * $promo_cart->promotionrule->discount_value;
                        $cartdiscounts = $cartdiscounts + $discount;
                        $promo_cart->disc_val_str = '-'.($promo_cart->promotionrule->discount_value * 100).'%';
                        $promo_cart->disc_val = '-'.($subtotal * $promo_cart->promotionrule->discount_value);
                    } elseif ($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') {
                        $discount = $promo_cart->promotionrule->discount_value;
                        $cartdiscounts = $cartdiscounts + $discount;
                        $promo_cart->disc_val_str = '-';
                        $promo_cart->disc_val = '-'.$promo_cart->promotionrule->discount_value + 0;
                    }
                    $subtotalaftercartpromo = $subtotalaftercartpromo - $discount;
                    $acquired_promo_carts[] = $promo_cart;
                }
            }
            $total_discount = $total_discount + $cartdiscounts;

            $subtotalaftercartcoupon = $subtotal;
            $available_coupon_carts = array();
            foreach($coupon_carts as $coupon_cart){
                // dd($coupon_cart->couponrule->rule_value);
                if($subtotal >= $coupon_cart->couponrule->rule_value){
                    if($coupon_cart->couponrule->rule_type == 'cart_discount_by_percentage') {
                        $discount = $subtotal * $coupon_cart->couponrule->discount_value;
                        $cartdiscounts = $cartdiscounts + $discount;
                        $coupon_cart->disc_val_str = '-'.($coupon_cart->couponrule->discount_value * 100).'%';
                        $coupon_cart->disc_val = '-'.($subtotal * $coupon_cart->couponrule->discount_value);
                    } elseif ($coupon_cart->couponrule->rule_type == 'cart_discount_by_value') {
                        $discount = $coupon_cart->couponrule->discount_value;
                        $cartdiscounts = $cartdiscounts + $discount;
                        $coupon_cart->disc_val_str = '-';
                        $coupon_cart->disc_val = '-'.$coupon_cart->couponrule->discount_value + 0;
                    }
                    $subtotalaftercartcoupon = $subtotalaftercartcoupon - $discount;
                    $available_coupon_carts[] = $coupon_cart;
                }
            }
            $total_discount = $total_discount + $cartdiscounts;

            if($retailer->parent->vat_included === 'yes') {
                $total = $subtotalaftercartpromo;
            } else {
                $total = $subtotalaftercartpromo + $vat;
            }

            $cartsummary->subtotal = $subtotal;
            $cartsummary->subtotalaftercartpromo = $subtotalaftercartpromo;
            $cartsummary->subtotalaftercartcoupon = $subtotalaftercartcoupon;
            $cartsummary->acquired_promo_carts = $acquired_promo_carts;
            $cartsummary->vat = $vat;
            $cartsummary->total_to_pay = $total;
            $cartsummary->total_discount = $total_discount;

            return View::make('mobile-ci.cart', array('page_title'=>Lang::get('mobileci.page_title.cart'), 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata, 'cartsummary' => $cartsummary, 'promotions' => $promo_products, 'promo_carts' => $promo_carts, 'coupon_carts' => $coupon_carts));
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function getTransferCartView()
    {
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartitems = $this->getCartForToolbar();

            return View::make('mobile-ci.transfer-cart', array('page_title'=>Lang::get('mobileci.page_title.transfercart'), 'retailer'=>$retailer, 'cartitems' => $cartitems));
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getPaymentView()
    {
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartitems = $this->getCartForToolbar();

            return View::make('mobile-ci.payment', array('page_title'=>Lang::get('mobileci.page_title.payment'), 'retailer'=>$retailer, 'cartitems' => $cartitems));
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
            $product_variant_id = OrbitInput::post('productvariantid');
            $quantity = OrbitInput::post('qty');

            $validator = \Validator::make(
                array(
                    'product_id' => $product_id,
                    'product_variant_id' => $product_variant_id,
                    'quantity' => $quantity,
                ),
                array(
                    'product_id' => 'required|orbit.exists.product',
                    'product_variant_id' => 'required|orbit.exists.productvariant',
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
            
            $cart->save();

            $cartdetail = CartDetail::excludeDeleted()->where('product_id', $product_id)->where('product_variant_id', $product_variant_id)->where('cart_id', $cart->cart_id)->first();
            if(empty($cartdetail)){
                $cartdetail = new CartDetail;
                $cartdetail->cart_id = $cart->cart_id;
                $cartdetail->product_id = $product->product_id;
                $cartdetail->product_variant_id = $product_variant_id;
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
            // return $this->redirectIfNotLoggedIn($e);
            return $e->getMessage();
        }
        
        return $this->render();
    }

    public function postDeleteFromCart()
    {
        try {
            $this->registerCustomValidation();

            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartdetailid = OrbitInput::post('detail');

            $validator = \Validator::make(
                array(
                    'cartdetailid' => $cartdetailid,
                ),
                array(
                    'cartdetailid' => 'required|orbit.exists.cartdetailid',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            
            $this->beginTransaction();
            
            $cartdetail = CartDetail::where('cart_detail_id', $cartdetailid)->excludeDeleted()->first();
            $cart = Cart::where('cart_id', $cartdetail->cart_id)->excludeDeleted()->first();

            $quantity = $cartdetail->quantity;
            $cart->total_item = $cart->total_item - $quantity;
            
            $cart->save();

            $cartdetail->delete();
            
            $cartdata = new stdclass();
            $cartdata->cart = $cart;
            $this->response->message = 'success';
            $this->response->data = $cartdata;

            $this->commit();
            return $this->render();

        } catch (Exception $e) {
            $this->rollback();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
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

            $product = Product::with('tax1', 'tax2')->where('product_id', $cartdetail->product_id)->first();

            $currentqty = $cartdetail->quantity;
            $deltaqty = $quantity - $currentqty;

            $cartdetail->quantity = $quantity;

            $cart->total_item = $cart->total_item + $deltaqty;

            $cart->save();

            $cartdetail->save();
            
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

        // Check promotion, it should exists
        Validator::extend('orbit.exists.promotion', function ($attribute, $value, $parameters) {
            $retailer = $this->getRetailerInfo();

            $promotion = Promotion::with(array('retailers' => function($q) use($retailer) 
                {
                    $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                }))->excludeDeleted()
                ->where('promotion_id', $value)
                ->first();

            if (empty($promotion)) {
                return FALSE;
            }

            \App::instance('orbit.validation.promotion', $promotion);

            return TRUE;
        });

        // Check coupon, it should exists
        Validator::extend('orbit.exists.coupon', function ($attribute, $value, $parameters) {
            $retailer = $this->getRetailerInfo();

            $coupon = Coupon::with(array('issueretailers' => function($q) use($retailer) 
                {
                    $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                }))->excludeDeleted()
                ->where('promotion_id', $value)
                ->first();

            if (empty($coupon)) {
                return FALSE;
            }

            \App::instance('orbit.validation.coupon', $coupon);

            return TRUE;
        });

        // Check product variant, it should exists
        Validator::extend('orbit.exists.productvariant', function ($attribute, $value, $parameters) {
            $product = \ProductVariant::excludeDeleted()
                        ->where('product_variant_id', $value)
                        ->first();

            if (empty($product)) {
                return FALSE;
            }

            \App::instance('orbit.validation.productvariant', $product);

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
        if($e->getMessage() === 'Session error: user not found.' || $e->getMessage() === 'Invalid session data.' || $e->getMessage() === 'IP address miss match.' || $e->getMessage() === 'Session has ben expires.' || $e->getMessage() === 'User agent miss match.') {
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
        return $cart->total_item;
    }

    protected function getCartData()
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
            $promo_products = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N" AND p.merchant_id = :merchantid
                inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id AND prr.retailer_id = :retailerid
                inner join ' . DB::getTablePrefix() . 'products prod on 
                (
                    (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
                    OR
                    (
                        (pr.discount_object_type="family") AND 
                        ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
                        ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
                        ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
                        ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
                        ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
                    )
                )'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id));

            $cartdetails = CartDetail::with(array('product' => function($q) {
                $q->where('products.status','active');
            }, 'variant' => function($q) {
                $q->where('product_variants.status','active');
            }))->where('status', 'active')->where('cart_id', $cart->cart_id)->get();
            $cartdata = new stdclass();
            $cartdata->cart = $cart;
            $cartdata->cartdetails = $cartdetails;

            return $cartdata;
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            return $e->getMessage();
        }
    }
}
