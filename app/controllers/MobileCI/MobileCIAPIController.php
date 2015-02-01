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
use \Widget;
use \EventModel;
use \Promotion;
use \Coupon;
use \CartCoupon;
use \IssuedCoupon;
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
            
            $coupons = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
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
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id));

            // $cartdata = $this->getCartForToolbar();
            if(empty(\Session::get('event'))) {
                \Session::put('event', 0);
                $event_counter = 0;
            } else {
                $event_counter = \Session::get('event');
            }

            $events = EventModel::excludeDeleted()->whereHas('retailers', function($q) use($retailer)
                {
                    $q->where('event_retailer.retailer_id', $retailer->merchant_id);
                })->where('merchant_id', $retailer->parent->merchant_id)-> orderBy('events.event_id', 'DESC')->skip($event_counter)->first();

            if(!empty($events)) {
                $event_counter++;
                \Session::put('event', $event_counter);
            }

            // \Session::forget('event');

            $cartitems = $this->getCartForToolbar();

            $widgets = Widget::excludeDeleted()->where('merchant_id', $retailer->parent->merchant_id)->whereHas('retailers', function($q) use($retailer)
            {
                $q->where('retailer_id', $retailer->merchant_id);
            })->orderBy('widget_order', 'ASC')->take(4)->get();

            // dd($widgets);
            return View::make('mobile-ci.home', array('page_title'=>Lang::get('mobileci.page_title.home'), 'retailer' => $retailer, 'new_products' => $new_products, 'promo_products' => $promo_products, 'promotion' => $promotion, 'cartitems' => $cartitems, 'coupons' => $coupons, 'events' => $events, 'widgets' => $widgets));
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
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();

            $sort_by = OrbitInput::get('sort_by');

            $pagetitle = Lang::get('mobileci.page_title.searching');

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
                $maxRecord = 300;
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

            // Filter by new product
            OrbitInput::get('new', function ($name) use ($products) {
                if(!empty($name)) {
                    $products->where(function($q) use ($name) {
                        $q->where('new_from', '<=', Carbon::now())->where('new_until', '>=', Carbon::now());
                    });
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
            
            $product_on_promo = array();
            foreach($promotions as $promotion) {
                $product_on_promo[] = $promotion->product_id;
            }

            OrbitInput::get('promo', function ($name) use ($products, $product_on_promo) {
                if(!empty($name)) {
                    if(!empty($product_on_promo)) {
                        $products->whereIn('products.product_id', $product_on_promo);
                    } else {
                        $products->where('product_id', '-1');
                    }
                }
            });

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
                inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
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
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id));

            $product_on_coupon = array();
            foreach($coupons as $coupon) {
                $product_on_coupon[] = $coupon->product_id;
            }

            // Filter by new product
            OrbitInput::get('coupon', function ($name) use ($products, $product_on_coupon) {
                if(!empty($name)) {
                    if(!empty($product_on_coupon)) {
                        $products->whereIn('products.product_id', $product_on_coupon);
                    } else {
                        $products->where('product_id', '-1');
                    }
                }
            });

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
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = count($listOfRec);
                $data->records = $listOfRec;
            }

            if(!empty(OrbitInput::get('new'))) {
                $pagetitle = 'NEW PRODUCTS';
            }
            if(!empty(OrbitInput::get('promo'))) {
                $pagetitle = 'PROMOTIONS';
            }
            if(!empty(OrbitInput::get('coupon'))) {
                $pagetitle = 'COUPONS';
            }
            return View::make('mobile-ci.search', array('page_title'=>$pagetitle, 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo));
            
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function getSearchPromotion()
    {
        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();

            $sort_by = OrbitInput::get('sort_by');

            $pagetitle = Lang::get('mobileci.page_title.searching');

            $promoid = OrbitInput::get('promoid');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'promotion_id' => $promoid,
                ),
                array(
                    'sort_by' => 'in:product_name,price',
                    'promotion_id' => 'required|orbit.exists.promotion',
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
                $maxRecord = 300;
            }

            $retailer = $this->getRetailerInfo();

            $products = Product::whereHas('retailers', function($query) use ($retailer) {
                            $query->where('retailer_id', $retailer->merchant_id);
                        })->where('merchant_id', $retailer->parent_id)->excludeDeleted();

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
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND p.promotion_id = :promid'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'promid' => $promoid));
            
            $product_on_promo = array();
            foreach($promotions as $promotion) {
                $product_on_promo[] = $promotion->product_id;
            }
           
            if(!empty($product_on_promo)) {
                $products->whereIn('products.product_id', $product_on_promo);
            } else {
                $products->where('product_id', '-1');
            }

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
                inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
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
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id));

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
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = count($listOfRec);
                $data->records = $listOfRec;
            }

            if(!empty($promotions)) {
                $pagetitle = 'PROMOTION : '.$promotions[0]->promotion_name;
            }
            
            return View::make('mobile-ci.promotions', array('page_title'=>$pagetitle, 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo));
            
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
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
                inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
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
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
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
                inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
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
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
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

            if(!empty($coupons)){
                $product->on_coupons = true;
            } else {
                $product->on_coupons = false;
            }

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
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postCloseCart()
    {
        try {
            $cartdata = $this->getCartData();

            if($cartdata->cart->moved_to_pos === 'Y') {
                $this->response->message = 'moved';
            } else {
                $this->response->message = 'notmoved';
            }

            return $this->render();
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postProductCouponPopup()
    {
        try {
            $this->registerCustomValidation();
            $product_id = OrbitInput::post('productid');

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

            $coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
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
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND prod.product_id = :productid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $product_id));

            // $promotion = Coupon::whereHas('issuedcoupons', function($q) use($user)
            //     {
            //         $q->excludeDeleted()->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now());
            //     })
            //     ->whereHas('redeemretailers', function($q) use($retailer)
            //     {
            //         $q->where('promotion_retailer_redeem', $retailer->merchant_id);
            //     })->excludeDeleted()->where('promotion_type', 'product')->first();

            $this->response->message = 'success';
            $this->response->data = $coupons;

            return $this->render();
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postCartProductCouponPopup()
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

            $coupon = Coupon::excludeDeleted()->where('promotion_id', $promotion_id)->first();

            $this->response->message = 'success';
            $this->response->data = $coupon;

            return $this->render();
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    // public function getCartView()
    // {
    //     try {
    //         $user = $this->getLoggedInUser();

    //         $retailer = $this->getRetailerInfo();
            
    //         $cartitems = $this->getCartForToolbar();

    //         $cartdata = $this->getCartData();

    //         $cartsummary = new stdclass();

    //         $subtotal = 0;
    //         $vat = 0;
    //         $total = 0;
    //         $total_discount = 0;

    //         $promo_products = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
    //             inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N" AND p.merchant_id = :merchantid
    //             inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id AND prr.retailer_id = :retailerid
    //             inner join ' . DB::getTablePrefix() . 'products prod on 
    //             (
    //                 (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id) 
    //                 OR
    //                 (
    //                     (pr.discount_object_type="family") AND 
    //                     ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND 
    //                     ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
    //                     ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
    //                     ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
    //                     ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
    //                 )
    //             )'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id));

    //         // check for cart based promotions
    //         $promo_carts = Promotion::with('promotionrule')->excludeDeleted()->where('is_coupon', 'N')->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('retailers', function($q) use ($retailer)
    //             {
    //                 $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
    //             })
    //             ->where(function($q) 
    //             {
    //                 $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(function($qr)
    //                 {
    //                     $qr->where('begin_date', '<=', Carbon::now())->where('is_permanent', '=', 'Y');
    //                 });
    //             })
    //             ->get();

    //         foreach ($cartdata->cartdetails as $cartdetail) {
    //             $variant = \ProductVariant::where('product_variant_id', $cartdetail->product_variant_id)->excludeDeleted()->first();
    //             $product = Product::with('tax1', 'tax2')->where('product_id', $variant->product_id)->excludeDeleted()->first();
                
    //             $filtered = array_filter($promo_products, function($v) use ($product) { return $v->product_id == $product->product_id; });

    //             $discount = 0;
    //             foreach($filtered as $promotion){
    //                 if($promotion->product_id == $product->product_id) {
    //                     if($promotion->rule_type == 'product_discount_by_percentage') {
    //                         $discount = $discount +  ( $variant->price * $promotion->discount_value);
    //                     } elseif ($promotion->rule_type == 'product_discount_by_value') {
    //                         $discount = $discount + $promotion->discount_value;
    //                     }
    //                 }
    //             }
                
    //             $subtotal = $subtotal + (($variant->price - $discount) * $cartdetail->quantity);
    //             $priceaftertax = ($variant->price - $discount) * $cartdetail->quantity;
    //             if(!is_null($product->tax1)) {
    //                 $vat1 = $product->tax1->tax_value * ($variant->price - $discount) * $cartdetail->quantity;
    //                 $vat = $vat + $vat1;
    //                 $priceaftertax = $priceaftertax + $vat1;
    //             }
    //             if(!is_null($product->tax2)) {
    //                 $vat2 = $product->tax2->tax_value * ($variant->price - $discount) * $cartdetail->quantity;
    //                 $vat = $vat + $vat2;
    //                 $priceaftertax = $priceaftertax + $vat2;
    //             }

    //             $total_discount = $total_discount + ($discount * $cartdetail->quantity);

    //             $attributes = array();
    //             if($cartdetail->attributeValue1['value']){
    //                 $attributes[] = $cartdetail->attributeValue1['value'];
    //             }
    //             if($cartdetail->attributeValue2['value']){
    //                 $attributes[] = $cartdetail->attributeValue2['value'];
    //             }
    //             if($cartdetail->attributeValue3['value']){
    //                 $attributes[] = $cartdetail->attributeValue3['value'];
    //             }
    //             if($cartdetail->attributeValue4['value']){
    //                 $attributes[] = $cartdetail->attributeValue4['value'];
    //             }
    //             if($cartdetail->attributeValue5['value']){
    //                 $attributes[] = $cartdetail->attributeValue5['value'];
    //             }

    //             $cartdetail->promoforthisproducts = $filtered;
    //             $cartdetail->attributes = $attributes;
    //             $cartdetail->priceafterpromo = $variant->price - $discount;
    //             $cartdetail->ammountbeforepromo = $variant->price * $cartdetail->quantity;
    //             $cartdetail->ammountafterpromo = ($variant->price - $discount) * $cartdetail->quantity;
    //             $cartdetail->ammountaftertax = $priceaftertax;
    //         }

    //         $used_product_coupons = CartCoupon::with(array('cartdetail' => function($q) 
    //         {
    //             $q->join('product_variants', 'cart_details.product_variant_id', '=', 'product_variants.product_variant_id');
    //         }, 'issuedcoupon' => function($q) use($user)
    //         {
    //             $q->where('issued_coupons.user_id', $user->user_id)
    //             ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
    //             ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');
    //         }))->whereHas('issuedcoupon', function($q) use($user)
    //         {
    //             $q->where('issued_coupons.user_id', $user->user_id);
    //         })->whereHas('cartdetail', function($q)
    //         {
    //             $q->where('cart_coupons.object_type', '=', 'cart_detail');
    //         })->get();

    //         // dd($used_product_coupons);

    //         $used_cart_coupons = CartCoupon::with(array('cart', 'issuedcoupon' => function($q) use($user)
    //         {
    //             $q->where('issued_coupons.user_id', $user->user_id)
    //             ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
    //             ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');
    //         }))
    //         ->whereHas('cart', function($q) use($cartdata)
    //         {
    //             $q->where('cart_coupons.object_type', '=', 'cart')
    //             ->where('cart_coupons.object_id', '=', $cartdata->cart->cart_id);
    //         })
    //         ->where('cart_coupons.object_type', '=', 'cart')->get();
    //         // dd($used_cart_coupons);

    //         $subtotalbeforecartcartcoupon = $subtotal;
            
    //         foreach($used_product_coupons as $used_product_coupon) {
    //             if($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_percentage') {
    //                 $used_product_coupon->disc_val_str = '-'.($used_product_coupon->issuedcoupon->discount_value * 100).'%';
    //                 $used_product_coupon->disc_val = '-'.($used_product_coupon->issuedcoupon->discount_value * $used_product_coupon->cartdetail->price);
    //                 $subtotal = $subtotal - ($used_product_coupon->issuedcoupon->discount_value * $used_product_coupon->cartdetail->price);
    //             } elseif($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_value') {
    //                 $used_product_coupon->disc_val_str = '-'.$used_product_coupon->issuedcoupon->discount_value + 0;
    //                 $used_product_coupon->disc_val = '-'.$used_product_coupon->issuedcoupon->discount_value + 0;
    //                 $subtotal = $subtotal - $used_product_coupon->issuedcoupon->discount_value;
    //             }
    //         }

    //         // check for available cart based coupons
    //         $coupon_carts = Coupon::join('promotion_rules', function($q) use($subtotal)
    //         {
    //             $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.discount_object_type', '=', 'cash_rebate')->where('promotion_rules.coupon_redeem_rule_value', '<=', $subtotal);
    //         })->excludeDeleted()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function($q) use ($retailer)
    //         {
    //             $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
    //         })
    //         ->whereHas('issuedcoupons',function($q) use($user)
    //         {
    //             $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
    //         })->with(array('issuedcoupons' => function($q) use($user)
    //         {
    //             $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
    //         }))
    //         ->where(function($q) 
    //         {
    //             $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(function($qr)
    //             {
    //                 $qr->where('begin_date', '<=', Carbon::now())->where('is_permanent', '=', 'Y');
    //             });
    //         })
    //         ->get();
    //         // dd($promo_carts);
    //         $subtotalbeforecartpromo = $subtotal;

    //         $cartdiscounts = 0;
    //         $subtotalaftercartpromo = $subtotal;
    //         $acquired_promo_carts = array();
    //         foreach($promo_carts as $promo_cart){
    //             // dd($promo_cart->promotionrule->rule_value);
    //             if($subtotal >= $promo_cart->promotionrule->rule_value){
    //                 if($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') {
    //                     $discount = $subtotal * $promo_cart->promotionrule->discount_value;
    //                     $cartdiscounts = $cartdiscounts + $discount;
    //                     $promo_cart->disc_val_str = '-'.($promo_cart->promotionrule->discount_value * 100).'%';
    //                     $promo_cart->disc_val = '-'.($subtotal * $promo_cart->promotionrule->discount_value);
    //                 } elseif ($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') {
    //                     $discount = $promo_cart->promotionrule->discount_value;
    //                     $cartdiscounts = $cartdiscounts + $discount;
    //                     $promo_cart->disc_val_str = '-'.$promo_cart->promotionrule->discount_value + 0;
    //                     $promo_cart->disc_val = '-'.$promo_cart->promotionrule->discount_value + 0;
    //                 }
    //                 $subtotalaftercartpromo = $subtotalaftercartpromo - $discount;
    //                 $acquired_promo_carts[] = $promo_cart;
    //             }
    //         }
    //         $total_discount = $total_discount + $cartdiscounts;

    //         $subtotalaftercartcoupon = $subtotal;

    //         $cart_discount_by_percentage_counter = 0;

    //         $total_cart_coupon_discount = 0;
    //         $acquired_coupon_carts = array();
    //         // dd($subtotalbeforecartpromo);
    //         foreach($used_cart_coupons as $used_cart_coupon) {
    //             // dd($used_cart_coupon);
    //             // dd($used_cart_coupon->issuedcoupon->coupon_redeem_rule_value);
    //             if(!empty($used_cart_coupon->issuedcoupon->coupon_redeem_rule_value)) {
    //                 if($subtotalbeforecartpromo >= $used_cart_coupon->issuedcoupon->coupon_redeem_rule_value) {
    //                     if($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
    //                         $used_cart_coupon->disc_val_str = '-'.($used_cart_coupon->issuedcoupon->discount_value * 100).'%';
    //                         $used_cart_coupon->disc_val = '-'.($used_cart_coupon->issuedcoupon->discount_value * $subtotal);
    //                         $discount = $subtotal * $used_cart_coupon->issuedcoupon->discount_value;
    //                         $cart_discount_by_percentage_counter++;
    //                     } elseif($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
    //                         $used_cart_coupon->disc_val_str = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
    //                         $used_cart_coupon->disc_val = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
    //                         $discount = $used_cart_coupon->issuedcoupon->discount_value;
    //                     }
    //                     $total_cart_coupon_discount = $total_cart_coupon_discount+$discount;
    //                     $acquired_coupon_carts[] = $used_cart_coupon;
    //                     // $used_cart_coupon->disc_val = 'asdasd';
    //                 } else {
    //                     $this->beginTransaction();
    //                     $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $used_cart_coupon->issued_coupon_id)->first();
    //                     $issuedcoupon->makeActive();
    //                     $issuedcoupon->save();
    //                     $used_cart_coupon->delete(TRUE);
    //                     $this->commit();
    //                 }
    //             }
    //         }
        
    //         $available_coupon_carts = array();
            
    //         if(!empty($coupon_carts)) {
    //             foreach($coupon_carts as $coupon_cart) {
    //                 // dd($coupon_cart);
    //                 if($subtotalbeforecartpromo >= $coupon_cart->coupon_redeem_rule_value){
    //                     if($coupon_cart->rule_type == 'cart_discount_by_percentage') {
    //                         if($cart_discount_by_percentage_counter == 0) { // prevent more than one cart_discount_by_percentage
    //                             $discount = $subtotal * $coupon_cart->discount_value;
    //                             $cartdiscounts = $cartdiscounts + $discount;
    //                             $coupon_cart->disc_val_str = '-'.($coupon_cart->discount_value * 100).'%';
    //                             $coupon_cart->disc_val = '-'.($subtotal * $coupon_cart->discount_value);
    //                             $available_coupon_carts[] = $coupon_cart;
    //                             // $used_cart_coupon->disc_val = 'asdasd';
    //                         }
    //                     } elseif ($coupon_cart->rule_type == 'cart_discount_by_value') {
    //                         $discount = $coupon_cart->discount_value;
    //                         $cartdiscounts = $cartdiscounts + $discount;
    //                         $coupon_cart->disc_val_str = '-'.$coupon_cart->discount_value + 0;
    //                         $coupon_cart->disc_val = '-'.$coupon_cart->discount_value + 0;
    //                         $available_coupon_carts[] = $coupon_cart;
    //                     }
    //                     $subtotalaftercartcoupon = $subtotalaftercartcoupon - $discount;
    //                 } else {
    //                     $coupon_cart->disc_val = $coupon_cart->rule_value;
    //                 }
    //             }
    //         }

    //         $total_discount = $total_discount + $cartdiscounts;

    //         // dd($coupon_carts);

    //         if($retailer->parent->vat_included === 'yes') {
    //             $total = $subtotalaftercartpromo - $total_cart_coupon_discount;
    //         } else {
    //             $total = $subtotalaftercartpromo - $total_cart_coupon_discount + $vat;
    //         }

    //         $cartsummary->subtotal = $subtotalaftercartpromo - $total_cart_coupon_discount;
    //         $cartsummary->subtotalaftercartpromo = $subtotalaftercartpromo;
    //         $cartsummary->subtotalaftercartcoupon = $subtotalaftercartcoupon;
    //         $cartsummary->subtotalbeforecartpromo = $subtotalbeforecartpromo;
    //         $cartsummary->acquired_promo_carts = $acquired_promo_carts;
    //         $cartsummary->vat = $vat;
    //         $cartsummary->total_to_pay = $total;
    //         $cartsummary->total_discount = $total_discount;

    //         return View::make('mobile-ci.cart', array('page_title'=>Lang::get('mobileci.page_title.cart'), 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata, 'cartsummary' => $cartsummary, 'promotions' => $promo_products, 'promo_carts' => $promo_carts, 'coupon_carts' => $coupon_carts, 'used_product_coupons' => $used_product_coupons, 'used_cart_coupons' => $acquired_coupon_carts, 'available_coupon_carts' => $available_coupon_carts));
    //     } catch (Exception $e) {
    //         // return $this->redirectIfNotLoggedIn($e);
    //         return $e;
    //     }
    // }

    public function getCartView()
    {
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();
            
            $cartitems = $this->getCartForToolbar();

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

            $cartdetails = CartDetail::with(array('product' => function($q) {
                $q->where('products.status','active');
            }, 'variant' => function($q) {
                $q->where('product_variants.status','active');
            }), 'tax1', 'tax2')->where('status', 'active')->where('cart_id', $cart->cart_id)->get();
            $cartdata = new stdclass();
            $cartdata->cart = $cart;
            $cartdata->cartdetails = $cartdetails;

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
            
            $used_product_coupons = CartCoupon::with(array('cartdetail' => function($q) 
            {
                $q->join('product_variants', 'cart_details.product_variant_id', '=', 'product_variants.product_variant_id');
            }, 'issuedcoupon' => function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id)
                ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');
            }))->whereHas('issuedcoupon', function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id);
            })->whereHas('cartdetail', function($q)
            {
                $q->where('cart_coupons.object_type', '=', 'cart_detail');
            })->get();
            // dd($used_product_coupons);

            $subtotal = 0;
            $vat = 0;
            $total = 0;
            $attributes = array();
            
            $vat_included = $retailer->parent->vat_included;

            if($vat_included === 'yes') {
                
                foreach($cartdata->cartdetails as $cartdetail) {
                    $product_vat = 0;
                    $original_price = $cartdetail->variant->price;
                    $original_ammount = $original_price * $cartdetail->quantity;
                    $ammount_after_promo = $original_ammount;
                    $product_price_wo_tax = $original_price;

                    if(!is_null($cartdetail->tax1)) {
                        $vat1 = $cartdetail->tax1->tax_value * $original_price;
                        $product_vat = $product_vat + $vat1;
                        $product_price_wo_tax = $product_price_wo_tax - $vat1;
                        // $vat = $vat + $vat1;
                    }
                    if(!is_null($cartdetail->tax2)) {
                        $vat2 = $cartdetail->tax2->tax_value * $original_price;
                        $product_vat = $product_vat + $vat2;
                        $product_price_wo_tax = $product_price_wo_tax - $vat2;
                        // $vat = $vat + $vat2;
                    }

                    $promo_filters = array_filter($promo_products, function($v) use ($cartdetail) { return $v->product_id == $cartdetail->product_id; });
                    foreach($promo_filters as $promo_filter) {
                        if($promo_filter->rule_type == 'product_discount_by_percentage') {
                            $discount = $promo_filter->discount_value * $original_price;
                            $promo_filter->discount_str = $promo_filter->discount_value * 100;
                        } elseif($promo_filter->rule_type == 'product_discount_by_value') {
                            $discount = $promo_filter->discount_value;
                            $promo_filter->discount_str = $promo_filter->discount_value;
                        }
                        $promo_filter->discount = $discount * $cartdetail->quantity;
                        $ammount_after_promo = $ammount_after_promo - $promo_filter->discount;
                    }
                    $cartdetail->promo_for_this_product = $promo_filters;

                    $coupon_filter = array();
                    foreach($used_product_coupons as $used_product_coupon) {
                        if($used_product_coupon->cartdetail->product_id == $cartdetail->product->product_id) {
                            if($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_percentage') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value * $original_price;
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value * 100;
                            } elseif($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_value') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                            }
                            $used_product_coupon->discount = $discount;
                            $ammount_after_promo = $ammount_after_promo - $discount;
                            $coupon_filter[] = $used_product_coupon;
                        }
                    }
                    $cartdetail->coupon_for_this_product = $coupon_filter;
                    
                    $cartdetail->original_price = $original_price;
                    $cartdetail->original_ammount = $original_ammount;
                    $cartdetail->ammount_after_promo = $ammount_after_promo;

                    

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
                    $cartdetail->attributes = $attributes;
                    $subtotal = $subtotal + $ammount_after_promo;
                }

                $cartsummary = new stdclass();
                $cartsummary->vat = $vat;
                $cartsummary->total_to_pay = $subtotal;
                $cartdata->cartsummary = $cartsummary;
            } else {

            }

            
            // print_r($cartdata);

            return View::make('mobile-ci.cart', array('page_title'=>Lang::get('mobileci.page_title.cart'), 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata, 'attribute' => $attributes));
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

            $cartdata = $this->getCartData();

            return View::make('mobile-ci.transfer-cart', array('page_title'=>Lang::get('mobileci.page_title.transfercart'), 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata));
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
            $coupons = (array) OrbitInput::post('coupons');

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
            
            foreach($coupons as $coupon) {
                $validator = \Validator::make(
                    array(
                        'coupon' => $coupon,
                    ),
                    array(
                        'coupon' => 'orbit.exists.issuedcoupons',
                    ),
                    array(
                        'coupon' => 'Coupon not exists',
                    )
                );

                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $used_coupons = IssuedCoupon::excludeDeleted()->where('issued_coupon_id', $coupon)->first();
                $cartcoupon = new CartCoupon;
                $cartcoupon->issued_coupon_id = $coupon;
                $cartcoupon->object_type = 'cart_detail';
                $cartcoupon->object_id = $cartdetail->cart_detail_id;
                $cartcoupon->save();
                $used_coupons->status = 'deleted';
                $used_coupons->save();
            }

            $coupons = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
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
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND prod.product_id = :productid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $product->product_id));
            
            $cartdetail->available_coupons = $coupons;
            
            $this->response->message = 'success';
            $this->response->data = $cartdetail;

            $this->commit();

        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
        
        return $this->render();
    }

    public function postAddCouponCartToCart()
    {
        try {
            $this->registerCustomValidation();

            $retailer = $this->getRetailerInfo();

            $user = $this->getLoggedInUser();

            $couponid = OrbitInput::post('detail');

            $validator = \Validator::make(
                array(
                    'couponid' => $couponid,
                ),
                array(
                    'couponid' => 'required|orbit.exists.issuedcoupons',
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

            $used_coupons = IssuedCoupon::excludeDeleted()->where('issued_coupon_id', $couponid)->first();
            
            $cartcoupon = new CartCoupon;
            $cartcoupon->issued_coupon_id = $couponid;
            $cartcoupon->object_type = 'cart';
            $cartcoupon->object_id = $cart->cart_id;
            $cartcoupon->save();
            
            $used_coupons->status = 'deleted';
            $used_coupons->save();

            $this->response->message = 'success';

            $this->commit();

        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
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
            
            $cartcoupons = CartCoupon::where('object_type', 'cart_detail')->where('object_id', $cartdetail->cart_detail_id)->get();

            if(!empty($cartcoupons)) {
                foreach($cartcoupons as $cartcoupon) {
                    $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $cartcoupon->issued_coupon_id)->first();
                    // dd($issuedcoupon);
                    $issuedcoupon->makeActive();
                    $issuedcoupon->save();
                    $cartcoupon->delete(TRUE);
                }
            }

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

    public function postDeleteCouponFromCart()
    {
        try {
            $this->registerCustomValidation();

            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $couponid = OrbitInput::post('detail');

            $this->beginTransaction();
            
            $cartcoupon = CartCoupon::whereHas('issuedcoupon', function($q) use($user, $couponid)
            {
                $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.issued_coupon_id', $couponid);
            })->first();

            if(!empty($cartcoupon)) {
                $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $cartcoupon->issued_coupon_id)->first();
                $issuedcoupon->makeActive();
                $issuedcoupon->save();
                $cartcoupon->delete(TRUE);
            }

            $this->response->message = 'success';

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

        // Check coupons, it should exists
        Validator::extend('orbit.exists.issuedcoupons', function ($attribute, $value, $parameters) {
            $retailer = $this->getRetailerInfo();

            $user = $this->getLoggedInUser();
           
            $coupon = Coupon::whereHas('issuedcoupons', function($q) use($user, $value)
                {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.issued_coupon_id', $value)->where('expired_date', '>=', Carbon::now());
                })
                ->whereHas('redeemretailers', function($q) use($retailer)
                {
                    $q->where('promotion_retailer_redeem.retailer_id', $retailer->merchant_id);
                })
                ->excludeDeleted()
                ->first();

            if (empty($coupon)) {
                return FALSE;
            }
            
            \App::instance('orbit.validation.issuedcoupons', $coupon);

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
