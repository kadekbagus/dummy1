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
use Illuminate\Support\Collection as Collection;
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
use \Activity;

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
                $response = \LoginAPIController::create('raw')->postRegisterUserInShop();
                if ($response->code !== 0)
                {
                    throw new Exception($response->message, $response->code);
                }
                $user = $response->data;
            }

            $retailer = $this->getRetailerInfo();
            $cart = Cart::where('status', 'active')->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->first();
            if (is_null($cart)) {
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
        if (is_null($email)) {
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
        $user = null;
        $activityPage = Activity::mobileci()
                            ->setActivityType('view');
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                WHERE ic.expired_date >= "'.Carbon::now().'" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id));
            
            // $event_counter = 0;
            // $cartdata = $this->getCartForToolbar();

            if (empty(\Cookie::get('event'))) {
                $event_store = array();
            } else {
                $event_store = \Cookie::get('event');
            }

            $events = EventModel::excludeDeleted()->whereHas('retailers', function($q) use($retailer)
                {
                    $q->where('event_retailer.retailer_id', $retailer->merchant_id);
                })->where('merchant_id', $retailer->parent->merchant_id);

            if(!empty($event_store)) {
                foreach($event_store as $event_idx) {
                    $events->where('event_id', '!=', $event_idx);
                }
            }

            $events = $events->orderBy('events.event_id', 'DESC')->first();
            
            $event_families = array();
            if(!empty($events)) {
                if($events->link_object_type == 'family') {
                    if(!empty($events->link_object_id1)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id1)->excludeDeleted()->first();
                    }
                    if(!empty($events->link_object_id2)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id2)->excludeDeleted()->first();
                    }
                    if(!empty($events->link_object_id3)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id3)->excludeDeleted()->first();
                    }
                    if(!empty($events->link_object_id4)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id4)->excludeDeleted()->first();
                    }
                    if(!empty($events->link_object_id5)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id5)->excludeDeleted()->first();
                    }
                }
            }
            
            $event_family_url_param = '';
            for($i = 0; $i <= count($event_families) - 1; $i++) {
                $event_family_url_param = $event_family_url_param . 'f' . ($i + 1) . '=' . $event_families[$i]->category_id;
                if($i < count($event_families) - 1) {
                    $event_family_url_param = $event_family_url_param . '&';
                }
            }
            // dd($event_family_url_param);
            if(!empty($events)) {
                $event_store[] = $events->event_id;
                \Cookie::queue('event', $event_store, 1440);
                // \Session::put('event', $event_store);
            }

            // \Session::forget('event');

            $cartitems = $this->getCartForToolbar();

            $widgets = Widget::with('media')->excludeDeleted()->where('merchant_id', $retailer->parent->merchant_id)->whereHas('retailers', function($q) use($retailer)
            {
                $q->where('retailer_id', $retailer->merchant_id);
            })->orderBy('widget_order', 'ASC')->take(4)->get();

            $activityPageNotes = sprintf('Page viewed: %s', 'Home');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_home')
                            ->setActivityNameLong('View (Home Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.home', array('page_title'=>Lang::get('mobileci.page_title.home'), 'retailer' => $retailer, 'new_products' => $new_products, 'promo_products' => $promo_products, 'promotion' => $promotion, 'cartitems' => $cartitems, 'coupons' => $coupons, 'events' => $events, 'widgets' => $widgets, 'event_families' => $event_families, 'event_family_url_param' => $event_family_url_param))->withCookie($event_store);
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Home');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_home')
                            ->setActivityNameLong('View (Home Page) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            return $this->redirectIfNotLoggedIn($e);
            // return $e;
        }
    }

    public function getSignInView()
    {
        try {
            $user = $this->getLoggedInUser();
            
            return \Redirect::to('/customer/welcome');
        } catch (Exception $e) {
            $retailer = $this->getRetailerInfo();
            $user_email = '';
            if($e->getMessage() === 'Session error: user not found.' || $e->getMessage() === 'Invalid session data.' || $e->getMessage() === 'IP address miss match.' || $e->getMessage() === 'User agent miss match.') {
                return View::make('mobile-ci.signin', array('retailer' => $retailer, 'user_email' => $user_email));
            } else {
                return View::make('mobile-ci.signin', array('retailer' => $retailer, 'user_email' => $user_email));
            }
        }
    }

    public function getCatalogueView()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();
            $families = Category::has('product1')->where('merchant_id', $retailer->parent_id)->excludeDeleted()->get();
            // dd($families);
            $cartitems = $this->getCartForToolbar();

            // $family1 = \Session::put('f1', 1);
            // $family2 = \Session::put('f2', 7);
            // $family3 = \Session::put('f3', 11);
            // $family4 = \Session::put('f4', 12);
            // $family5 = \Session::put('f5', 13);

            // \Session::forget('f1');
            // \Session::forget('f2');
            // \Session::forget('f3');
            // \Session::forget('f4');
            // \Session::forget('f5');

            $family1 = \Session::get('f1');
            $family2 = \Session::get('f2');
            $family3 = \Session::get('f3');
            $family4 = \Session::get('f4');
            $family5 = \Session::get('f5');

            if(!empty($family1) || !empty($family2) || !empty($family3) || !empty($family4) || !empty($family5)) {
                $hasFamily = 'yes';
            } else {
                $hasFamily = 'no';
            }
            $array_of_families = array();
            if(!empty($family1)) {
                $array_of_families_lvl1[] = $family1;
            }
            if(!empty($family2)) {
                $array_of_families_lvl2[] = $family1;
                $array_of_families_lvl2[] = $family2;
            }
            if(!empty($family3)) {
                $array_of_families_lvl3[] = $family1;
                $array_of_families_lvl3[] = $family2;
                $array_of_families_lvl3[] = $family3;
            }
            if(!empty($family4)) {
                $array_of_families_lvl4[] = $family1;
                $array_of_families_lvl4[] = $family2;
                $array_of_families_lvl4[] = $family3;
                $array_of_families_lvl4[] = $family4;
            }
            if(!empty($family5)) {
                $array_of_families_lvl5[] = $family1;
                $array_of_families_lvl5[] = $family2;
                $array_of_families_lvl5[] = $family3;
                $array_of_families_lvl5[] = $family4;
                $array_of_families_lvl5[] = $family5;
            }

            $lvl1 = null;
            $lvl2 = null;
            $lvl3 = null;
            $lvl4 = null;
            $lvl5 = null;

            if($hasFamily == 'yes') {
                if(!empty($family1)) {
                    $lvl1 = $this->getProductListCatalogue($array_of_families_lvl1, 1, $family1, '');
                }
                if(!empty($family2)) {
                    $lvl2 = $this->getProductListCatalogue($array_of_families_lvl2, 2, $family2, '');
                }
                if(!empty($family3)) {
                    $lvl3 = $this->getProductListCatalogue($array_of_families_lvl3, 3, $family3, '');
                }
                if(!empty($family4)) {
                    $lvl4 = $this->getProductListCatalogue($array_of_families_lvl4, 4, $family4, '');
                }
                if(!empty($family5)) {
                    $lvl5 = $this->getProductListCatalogue($array_of_families_lvl5, 5, $family5, '');
                }
            }
            
            $activityPageNotes = sprintf('Page viewed: %s', 'Catalogue');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_catalogue')
                            ->setActivityNameLong('View (Cataloguge Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.catalogue', array('page_title'=>Lang::get('mobileci.page_title.catalogue'), 'retailer' => $retailer, 'families' => $families, 'cartitems' => $cartitems, 'hasFamily' => $hasFamily, 'lvl1' => $lvl1, 'lvl2' => $lvl2, 'lvl3' => $lvl3, 'lvl4' => $lvl4, 'lvl5' => $lvl5));
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Catalogue');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_catalogue')
                            ->setActivityNameLong('View (Cataloguge Page) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getSearchProduct()
    {
        $user = null;
        $keyword = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();

            $sort_by = OrbitInput::get('sort_by');
            $keyword = trim(OrbitInput::get('keyword'));

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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                WHERE ic.expired_date >= "'.Carbon::now().'" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id));

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
                if (count($promo_for_this_product) > 0) {
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
                if (count($couponstocatch_this_product) > 0) {
                    $product->on_couponstocatch = true;
                } else {
                    $product->on_couponstocatch = false;
                }

                // set coupons flag
                $coupon_for_this_product = array_filter($coupons, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($coupon_for_this_product) > 0) {
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
            if($totalRec>$search_limit) {
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

            $activityPageNotes = sprintf('Page viewed: Search Page, keyword: %s', $keyword);
            $activityPage->setUser($user)
                            ->setActivityName('view_page_search')
                            ->setActivityNameLong('View (Search Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.search', array('page_title'=>$pagetitle, 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo));
            
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view: Search Page, keyword: %s', $keyword);
            $activityPage->setUser($user)
                            ->setActivityName('view_page_search')
                            ->setActivityNameLong('View (Search Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }


    public function getCategory()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
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

            $title = array();
            // Filter by category/family
            
            $title[] = OrbitInput::get('f1', function ($name) use ($products) {
                if(!empty($name)) {
                    $products->where('category_id1', $name);
                    $cat = Category::where('category_id', $name)->first()->category_name;
                    return $cat;
                }
            });

            $title[] = OrbitInput::get('f2', function ($name) use ($products) {
                if(!empty($name)) {
                    $products->where('category_id2', $name);
                    $cat = Category::where('category_id', $name)->first()->category_name;
                    return $cat;
                }
            });

            $title[] = OrbitInput::get('f3', function ($name) use ($products) {
                if(!empty($name)) {
                    $products->where('category_id3', $name);
                    $cat = Category::where('category_id', $name)->first()->category_name;
                    return $cat;
                }
            });

            $title[] = OrbitInput::get('f4', function ($name) use ($products) {
                if(!empty($name)) {
                    $products->where('category_id4', $name);
                    $cat = Category::where('category_id', $name)->first()->category_name;
                    return $cat;
                }
            });

            $title[] = OrbitInput::get('f5', function ($name) use ($products) {
                if(!empty($name)) {
                    $products->where('category_id5', $name);
                    $cat = Category::where('category_id', $name)->first()->category_name;
                    return $cat;
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                WHERE ic.expired_date >= "'.Carbon::now().'" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id));

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
                if (count($promo_for_this_product) > 0) {
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
                if (count($couponstocatch_this_product) > 0) {
                    $product->on_couponstocatch = true;
                } else {
                    $product->on_couponstocatch = false;
                }

                // set coupons flag
                $coupon_for_this_product = array_filter($coupons, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($coupon_for_this_product) > 0) {
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
            if($totalRec>$search_limit) {
                $data = new stdclass();
                $data->status = 0;
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = count($listOfRec);
                $data->records = $listOfRec;
            }

            if(!empty($title)) {
                $ttl = array_filter($title, function($v) { return !empty($v);});
                $pagetitle = implode(' / ', $ttl);
            }
            
            $activityPageNotes = sprintf('Page viewed: %s', 'Category');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_category')
                            ->setActivityNameLong('View (Category Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.category', array('page_title'=>$pagetitle, 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo));
            
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Category');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_category')
                            ->setActivityNameLong('View (Category Page) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function getSearchPromotion()
    {
        $user = null;
        $promoid = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
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

            $all_promotions = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
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

            $promotions = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N"
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                $promo_for_this_product = array_filter($all_promotions, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($promo_for_this_product) > 0) {
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
                if (count($couponstocatch_this_product) > 0) {
                    $product->on_couponstocatch = true;
                } else {
                    $product->on_couponstocatch = false;
                }

                // set coupons flag
                $coupon_for_this_product = array_filter($coupons, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($coupon_for_this_product) > 0) {
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
            if($totalRec>$search_limit) {
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
            $activityPageNotes = sprintf('Page viewed: Promotion Detail, Promotion Id: %s', $promoid);
            $activityPage->setUser($user)
                            ->setActivityName('view_page_promotion_detail')
                            ->setActivityNameLong('View (Promotion Detail Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.promotions', array('page_title'=>$pagetitle, 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo));
            
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: Promotion Detail, Promotion Id: %s', $promoid);
            $activityPage->setUser($user)
                            ->setActivityName('view_page_promotion_detail')
                            ->setActivityNameLong('View (Promotion Detail Page) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function getSearchCoupon()
    {
        $user = null;
        $promoid = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();

            $sort_by = OrbitInput::get('sort_by');

            $pagetitle = Lang::get('mobileci.page_title.searching');

            $promoid = OrbitInput::get('couponid');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'promotion_id' => $promoid,
                ),
                array(
                    'sort_by' => 'in:product_name,price',
                    'promotion_id' => 'required|orbit.exists.issuedcoupons',
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

            $all_promotions = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
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

            $promotions = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N"
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
            
            $product_on_promo = array();
            foreach($promotions as $promotion) {
                $product_on_promo[] = $promotion->product_id;
            }
           
            // if(!empty($product_on_promo)) {
            //     $products->whereIn('products.product_id', $product_on_promo);
            // } else {
            //     $products->where('product_id', '-1');
            // }

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
            
            $coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
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
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                WHERE ic.issued_coupon_id = :issuedid AND ic.expired_date >= "'.Carbon::now().'" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'issuedid' => $promoid));
            $product_on_coupon = array();
            foreach($coupons as $coupon) {
                $product_on_coupon[] = $coupon->product_id;
            }
           
            if(!empty($product_on_coupon)) {
                $products->whereIn('products.product_id', $product_on_coupon);
            } else {
                $products->where('product_id', '-1');
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
                $promo_for_this_product = array_filter($all_promotions, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($promo_for_this_product) > 0) {
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
                if (count($couponstocatch_this_product) > 0) {
                    $product->on_couponstocatch = true;
                } else {
                    $product->on_couponstocatch = false;
                }

                // set coupons flag
                $coupon_for_this_product = array_filter($coupons, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($coupon_for_this_product) > 0) {
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
            if($totalRec>$search_limit) {
                $data = new stdclass();
                $data->status = 0;
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = count($listOfRec);
                $data->records = $listOfRec;
            }

            if(!empty($coupons)) {
                $pagetitle = 'KUPON : '.$coupons[0]->promotion_name;
            }
            $activityPageNotes = sprintf('Page viewed: Coupon Detail, Issued Coupon Id: %s', $promoid);
            $activityPage->setUser($user)
                            ->setActivityName('view_page_coupon_detail')
                            ->setActivityNameLong('View (Coupon Detail Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.coupons', array('page_title'=>$pagetitle, 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_coupon, 'coupons' => $coupons));
            
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: Coupon Detail, Issued Coupon Id: %s', $promoid);
            $activityPage->setUser($user)
                            ->setActivityName('view_page_coupon_detail')
                            ->setActivityNameLong('View (Coupon Detail Page) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function getPromotionList()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $promotions = Promotion::with('promotionrule')->excludeDeleted()->where('is_coupon', 'N')->where('merchant_id', $retailer->parent_id)->whereHas('retailers', function($q) use ($retailer)
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
            
            if (count($promotions) > 0) {
                $data = new stdclass();
                $data->status = 1;
                $data->records = $promotions;
            } else {
                $data = new stdclass();
                $data->status = 0;
            }

            $cartitems = $this->getCartForToolbar();

            $activityPageNotes = sprintf('Page viewed: %s', 'Promotion List Page');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_promotion_list')
                            ->setActivityNameLong('View (Promotion List Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.promotion-list', array('page_title' => 'PROMOTIONS', 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems));
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Promotion List');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_promotion_list')
                            ->setActivityNameLong('View (Promotion List) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            return $e->getMessage();
        }
    }

    public function getCouponList()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $promotions = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y"
                inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
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
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
                WHERE ic.expired_date >= "'.Carbon::now().'" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "'. Carbon::now() .'" ORDER BY ic.expired_date ASC'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id));
            
            if (count($promotions) > 0) {
                $data = new stdclass();
                $data->status = 1;
                $data->records = $promotions;
            } else {
                $data = new stdclass();
                $data->status = 0;
            }

            $cartitems = $this->getCartForToolbar();
            
            $activityPageNotes = sprintf('Page viewed: %s', 'Coupon List Page');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_coupon_list')
                            ->setActivityNameLong('View (Coupon List Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.coupon-list', array('page_title' => 'KUPON SAYA', 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems));
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Coupon List');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_coupon_list')
                            ->setActivityNameLong('View (Coupon List) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            return $e->getMessage();
        }
    }

    public function getProductList()
    {
        $user = null;
        $family_id = null;
        $activityCategory = Activity::mobileci()
                            ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sort_by');
            $family_id = OrbitInput::get('family_id');
            $family_level = OrbitInput::get('family_level');
            $families = OrbitInput::get('families');

            if (count($families) == 1) {
                // dd($families);
                \Session::put('f1', $family_id);
                \Session::forget('f2');
                \Session::forget('f3');
                \Session::forget('f4');
                \Session::forget('f5');
            } elseif(count($families) == 2) {
                \Session::put('f2', $family_id);
                \Session::forget('f3');
                \Session::forget('f4');
                \Session::forget('f5');
            } elseif(count($families) == 3) {
                \Session::put('f3', $family_id);
                \Session::forget('f4');
                \Session::forget('f5');
            } elseif(count($families) == 4) {
                \Session::put('f4', $family_id);
                \Session::forget('f5');
            } elseif(count($families) == 5) {
                \Session::put('f5', $family_id);
            }

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
                        ->where(function($query) use($nextfamily)
                            {
                                $query->whereNotNull('products.category_id'.$nextfamily)->orWhere('products.category_id'.$nextfamily, '<>', 0);
                            })
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
                    $q->where(function($q2) use($i)
                        {
                            $q2->whereNull('category_id' . $i)->orWhere('category_id' . $i, 0);
                        });
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                WHERE ic.expired_date >= "'.Carbon::now().'" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id));

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
                if (count($promo_for_this_product) > 0) {
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
                if (count($couponstocatch_this_product) > 0) {
                    $product->on_couponstocatch = true;
                } else {
                    $product->on_couponstocatch = false;
                }

                // set coupons flag
                $coupon_for_this_product = array_filter($coupons, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($coupon_for_this_product) > 0) {
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
            // $search_limit = Config::get('orbit.shop.search_limit');
            // if($totalRec>$search_limit) {
            //     $data = new stdclass();
            //     $data->status = 0;
            // }else{
            $data = new stdclass();
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;
            // }

            $cartitems = $this->getCartForToolbar();
            
            $activityfamily = Category::where('category_id', $family_id)->first();

            $activityCategoryNotes = sprintf('Category viewed: %s', $activityfamily->category_name);
            $activityCategory->setUser($user)
                            ->setActivityName('view_category')
                            ->setActivityNameLong('View Category ' . $activityfamily->category_name)
                            ->setObject($activityfamily)
                            ->setNotes($activityCategoryNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.product-list', array('retailer' => $retailer, 'data' => $data, 'subfamilies' => $subfamilies, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo, 'couponstocatchs' => $couponstocatchs));
            
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            // if($e->getMessage() === 'Invalid session data.') {
                $activityCategoryNotes = sprintf('Category viewed: %s', $family_id);
                $activityCategory->setUser($user)
                                ->setActivityName('view_category')
                                ->setActivityNameLong('View Category Not Found')
                                ->setObject(null)
                                ->setNotes($e->getMessage())
                                ->responseFailed()
                                ->save();
                return $e->getMessage();
            // }
        }
        
    }

    public function getProductListCatalogue($families, $family_level, $family_id, $sort_by)
    {
        $user = null;
        try {
            $user = $this->getLoggedInUser();

            $this->registerCustomValidation();

            // $sort_by = OrbitInput::get('sort_by');
            // $family_id = OrbitInput::get('family_id');
            // $family_level = OrbitInput::get('family_level');
            // $families = OrbitInput::get('families');

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

            if($nextfamily < 6) {
                $subfamilies = Category::where('merchant_id', $retailer->parent_id)->whereHas('product'.$nextfamily, function($q) use ($family_id, $family_level, $families) {
                    $nextfamily = $family_level + 1;
                    for($i = 1; $i < count($families); $i++) {
                        $q->where('products.category_id'.$i, $families[$i-1]);
                    }

                    $q  ->where('products.category_id'.$family_level, $family_id)
                        ->where(function($query) use($nextfamily)
                            {
                                $query->whereNotNull('products.category_id'.$nextfamily)->orWhere('products.category_id'.$nextfamily, '<>', 0);
                            })
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
                    $q->where(function($q2) use($i)
                        {
                            $q2->whereNull('category_id' . $i)->orWhere('category_id' . $i, 0);
                        });
                }
            });

            $_products = clone $products;
            // dd($products->get());
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                WHERE ic.expired_date >= "'.Carbon::now().'" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id));

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
                if (count($promo_for_this_product) > 0) {
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
                if (count($couponstocatch_this_product) > 0) {
                    $product->on_couponstocatch = true;
                } else {
                    $product->on_couponstocatch = false;
                }

                // set coupons flag
                $coupon_for_this_product = array_filter($coupons, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($coupon_for_this_product) > 0) {
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
            // dd($subfamilies);
            // $listOfRec = $products;
            // $search_limit = Config::get('orbit.shop.search_limit');
            // if($totalRec>$search_limit) {
            //     $data = new stdclass();
            //     $data->status = 0;
            // }else{
            //     $data = new stdclass();
            //     $data->status = 1;
            //     $data->total_records = $totalRec;
            //     $data->returned_records = count($listOfRec);
            //     $data->records = $listOfRec;
            // }

            $data = new stdclass();
            $data->records = $listOfRec;
            $data->subfamilies = $subfamilies;
            $data->promotions = $promotions;
            $data->promo_products = $product_on_promo;
            $data->couponstocatchs = $couponstocatchs;

            return $data;
            // return View::make('mobile-ci.product-list', array('retailer' => $retailer, 'data' => $data, 'subfamilies' => $subfamilies, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo, 'couponstocatchs' => $couponstocatchs));
            
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            // if($e->getMessage() === 'Invalid session data.') {
                return $e->getMessage();
            // }
        }
        
    }

    public function getProductView()
    {
        $user = null;
        $product_id = 0;
        $activityProduct = Activity::mobileci()
                                   ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();
            $product_id = trim(OrbitInput::get('id'));
            $product = Product::with('variants', 'attribute1', 'attribute2', 'attribute3', 'attribute4', 'attribute5')->whereHas('retailers', function($query) use ($retailer) {
                            $query->where('retailer_id', $retailer->merchant_id);
                        })->excludeDeleted()->where('product_id', $product_id)->first();
            if (empty($product)) {
                throw new Exception('Product id ' . $product_id . ' not found');
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                WHERE ic.expired_date >= "'.Carbon::now().'" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND prod.product_id = :productid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $product->product_id));

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
            
            $prices = array();
            foreach($product->variants as $variant) {
                $prices[] = $variant->price;
                $promo_price = $variant->price;
                if(!empty($promo_products)) {
                    $promo_price = $variant->price;
                    foreach($promo_products as $promo_filter) {
                        if($promo_filter->rule_type == 'product_discount_by_percentage') {
                            $discount = $promo_filter->discount_value * $variant->price;
                        } elseif($promo_filter->rule_type == 'product_discount_by_value') {
                            $discount = $promo_filter->discount_value;
                        }
                        $promo_price = $promo_price - $discount;
                    }
                }
                $variant->promo_price = $promo_price;
            }
            // set minimum price
            $min_price = min($prices);
            $product->min_price = $min_price + 0;

            $min_promo_price = $product->min_price;
            if(!empty($promo_products)) {
                foreach($promo_products as $promo_filter) {
                    if($promo_filter->rule_type == 'product_discount_by_percentage') {
                        $discount = $promo_filter->discount_value * $product->min_price;
                    } elseif($promo_filter->rule_type == 'product_discount_by_value') {
                        $discount = $promo_filter->discount_value;
                    }
                    $min_promo_price = $min_promo_price - $discount;
                }
            }
            $product->min_promo_price = $min_promo_price;

            $cartitems = $this->getCartForToolbar();

            if(!empty($coupons)) {
                $product->on_coupons = true;
            } else {
                $product->on_coupons = false;
            }
    
            $activityProductNotes = sprintf('Product viewed: %s', $product->product_name);
            $activityProduct->setUser($user)
                            ->setActivityName('view_product')
                            ->setActivityNameLong('View Product')
                            ->setObject($product)
                            ->setNotes($activityProductNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.product', array('page_title' => strtoupper($product->product_name), 'retailer' => $retailer, 'product' => $product, 'cartitems' => $cartitems, 'promotions' => $promo_products, 'attributes' => $attributes, 'couponstocatchs' => $couponstocatchs, 'coupons' => $coupons));
        
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            $activityProductNotes = sprintf('Product viewed: %s', $product_id);
            $activityProduct->setUser($user)
                            ->setActivityName('view_product')
                            ->setActivityNameLong('View Product Not Found')
                            ->setObject(null)
                            ->setNotes($e->getMessage())
                            ->responseFailed()
                            ->save();
            return $e;
        }
    }

    public function postCartProductPopup()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');

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

            $activityPageNotes = sprintf('Popup viewed: %s', 'Product');
            $activityPage->setUser($user)
                            ->setActivityName('view_popup_product')
                            ->setActivityNameLong('View (Product Popup)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return $this->render();
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Popup: %s', 'Product');
            $activityPage->setUser($user)
                            ->setActivityName('view_popup_product')
                            ->setActivityNameLong('View (Product Popup) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();

            return $this->redirectIfNotLoggedIn($e);
            // return $e->getMessage();
        }
    }

    public function postCartPromoPopup()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
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

            $activityPageNotes = sprintf('Popup viewed: %s', 'Cart Promotion');
            $activityPage->setUser($user)
                            ->setActivityName('view_popup_cart_promo')
                            ->setActivityNameLong('View (Cart Promotion Popup)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return $this->render();
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Popup: %s', 'Cart Promotion');
            $activityPage->setUser($user)
                            ->setActivityName('view_popup_cart_promo')
                            ->setActivityNameLong('View (Cart Promotion Popup) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postCartCouponPopup()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
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

            $activityPageNotes = sprintf('Popup viewed: %s', 'Cart Coupon');
            $activityPage->setUser($user)
                            ->setActivityName('view_popup_cart_coupon')
                            ->setActivityNameLong('View (Cart Coupon Popup)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return $this->render();
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Popup: %s', 'Cart Coupon');
            $activityPage->setUser($user)
                            ->setActivityName('view_popup_cart_coupon')
                            ->setActivityNameLong('View (Cart Coupon Popup) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
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
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            $this->registerCustomValidation();
            $product_id = OrbitInput::post('productid');
            $product_variant_id = OrbitInput::post('productvariantid');

            $validator = \Validator::make(
                array(
                    'product_id' => $product_id, 
                    'product_variant_id' => $product_variant_id,
                ),
                array(
                    'product_id' => 'required|orbit.exists.product',
                    'product_variant_id' => 'required|orbit.exists.productvariant',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            // read promo discount by percentage first
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
                WHERE prod.product_id = :productid'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product_id));

            $promo_percentage_cumulative = 0;
            $promo_for_this_product = array_filter($promo_products, function($v) use ($product_id) { return $v->product_id == $product_id; });
            if (count($promo_for_this_product) > 0) {
                foreach($promo_for_this_product as $promotion) {
                    if($promotion->rule_type == 'product_discount_by_percentage') {
                        $promo_percentage_cumulative = $promo_percentage_cumulative + $promotion->discount_value;
                    }
                }
            }

            // count product discount by percentage, shouldn't have more than 100%.
            $coupon_counters = CartCoupon::whereHas('issuedcoupon', function($q) use($user, $product_id, $product_variant_id) {
                $q->where('issued_coupons.user_id', $user->user_id);
                $q->whereHas('coupon', function($q2) {
                    $q2->whereHas('couponrule', function($q3) {
                        $q3->where('promotion_rules.rule_type', 'product_discount_by_percentage');
                    });
                });
            })->whereHas('cartdetail', function($q4) use($product_variant_id) {
                $q4->where('cart_details.product_variant_id', $product_variant_id);
            })->with('issuedcoupon.coupon.couponrule')->get();
            // dd($percentage_coupon_counter[0]->issuedcoupon->coupon->couponrule->rule_type);
            $coupon_percentage_cumulative = 0;
            foreach ($coupon_counters as $coupon_counter) {
                $coupon_percentage_cumulative = $coupon_percentage_cumulative + $coupon_counter->issuedcoupon->coupon->couponrule->discount_value;
            }
            // dd($coupon_percentage_cumulative);
            $percentage_prevent = '';
            // if($coupon_percentage_cumulative >= 1) {
            //     $percentage_prevent = ' pr.rule_type <> "product_discount_by_percentage" AND ';
            // }

            $coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                WHERE 
                    ic.expired_date >= NOW() 
                    AND p.merchant_id = :merchantid 
                    AND prr.retailer_id = :retailerid 
                    AND ic.user_id = :userid 
                    AND prod.product_id = :productid 
                    
                '), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $product_id));

            // $promotion = C oupon::whereHas('issuedcoupons', function($q) use($user)
            //     {
            //         $q->excludeDeleted()->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now());
            //     })
            //     ->whereHas('redeemretailers', function($q) use($retailer)
            //     {
            //         $q->where('promotion_retailer_redeem', $retailer->merchant_id);
            //     })->excludeDeleted()->where('promotion_type', 'product')->first();

            $this->response->message = 'success';
            $this->response->data = $coupons;
            // dd($coupons);
            $activityPageNotes = sprintf('Popup viewed: %s', 'Product Coupon');
            $activityPage->setUser($user)
                            ->setActivityName('view_popup_product_coupon')
                            ->setActivityNameLong('View (Product Coupon Popup)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return $this->render();
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Popup: %s', 'Product Coupon');
            $activityPage->setUser($user)
                            ->setActivityName('view_popup_product_coupon')
                            ->setActivityNameLong('View (Product Coupon Popup) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postCartProductCouponPopup()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
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

            $activityPageNotes = sprintf('Popup viewed: %s', 'Cart Product Coupon');
            $activityPage->setUser($user)
                            ->setActivityName('view_popup__cart_product_coupon')
                            ->setActivityNameLong('View (Cart Product Coupon Popup)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();


            return $this->render();
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Popup: %s', 'Cart Product Coupon');
            $activityPage->setUser($user)
                            ->setActivityName('view_popup_cart_product_coupon')
                            ->setActivityNameLong('View (Cart Product Coupon Popup) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function getCartView()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();
            
            $cartitems = $this->getCartForToolbar();

            $cart = Cart::where('status', 'active')->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->first();
            if (is_null($cart)) {
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
            })->get();

            $used_cart_coupons = CartCoupon::with(array('cart', 'issuedcoupon' => function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id)
                ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');
            }))
            ->whereHas('cart', function($q) use($cartdata)
            {
                $q->where('cart_coupons.object_type', '=', 'cart')
                ->where('cart_coupons.object_id', '=', $cartdata->cart->cart_id);
            })
            ->where('cart_coupons.object_type', '=', 'cart')->get();

            $subtotal = 0;
            $subtotal_wo_tax = 0;
            $vat = 0;
            $total = 0;

            $taxes = \MerchantTax::excludeDeleted()->where('merchant_id', $retailer->parent_id)->get();
            
            $vat_included = $retailer->parent->vat_included;

            if($vat_included === 'yes') {
                foreach($cartdata->cartdetails as $cartdetail) {
                    $attributes = array();
                    $product_vat_value = 0;
                    $original_price = $cartdetail->variant->price;
                    $original_ammount = $original_price * $cartdetail->quantity;
                    $ammount_after_promo = $original_ammount;
                    $product_price_wo_tax = $original_price;

                    $available_product_coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                            inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                            WHERE 
                                ic.expired_date >= NOW() 
                                AND p.merchant_id = :merchantid 
                                AND prr.retailer_id = :retailerid 
                                AND ic.user_id = :userid 
                                AND prod.product_id = :productid 
                                
                            '), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $cartdetail->product_id));
 
                    $cartdetail->available_product_coupons = count($available_product_coupons);

                    if(!is_null($cartdetail->tax1)) {
                        $tax1 = $cartdetail->tax1->tax_value;
                        if(!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            if($cartdetail->tax2->tax_type == 'service') {
                                $pwot  = $original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                                $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                $tax1_value = ($original_price / (1 + $tax1 + $tax2)) * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            }
                        } else {
                            $tax1_value = ($original_price / (1 + $tax1)) * $tax1;
                            $tax1_total_value = $tax1_value * $cartdetail->quantity;
                        }
                        foreach($taxes as $tax) {
                            if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax + $tax1_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax1_total_value;
                            }
                        }
                    } else {
                        $tax1 = 0;
                    }
                    
                    if(!is_null($cartdetail->tax2)) {
                        $tax2 = $cartdetail->tax2->tax_value;
                        if(!is_null($cartdetail->tax1)) {
                            if($cartdetail->tax2->tax_type == 'service') {
                                $tax2_value = ($original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                                $tax2_total_value = $tax2_value * $cartdetail->quantity;
                            } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                $tax2_value = ($original_price / (1 + $tax1 + $tax2)) * $tax2;
                                $tax2_total_value = $tax2_value * $cartdetail->quantity;
                            }
                        }
                        foreach($taxes as $tax) {
                            if($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax + $tax2_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax2_total_value;
                            }
                        }
                    } else {
                        $tax2 = 0;
                    }

                    // $product_price_wo_tax = $original_price / (1 + $product_vat_value);
                    if(!is_null($cartdetail->tax2)) {
                        if($cartdetail->tax2->tax_type == 'service') {
                            $product_price_wo_tax = $original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                        } elseif($cartdetail->tax2->tax_type == 'luxury') {
                            $product_price_wo_tax = $original_price / (1 + $tax1 + $tax2);
                        }
                    } else {
                        $product_price_wo_tax = $original_price / (1 + $tax1);
                    }
                    // dd($product_price_wo_tax);
                    $product_vat = ($original_price - $product_price_wo_tax) * $cartdetail->quantity;
                    $vat = $vat + $product_vat;
                    $product_price_wo_tax = $product_price_wo_tax * $cartdetail->quantity;
                    $subtotal = $subtotal + $original_ammount;
                    $subtotal_wo_tax = $subtotal_wo_tax + $product_price_wo_tax;

                    $temp_price = $original_ammount;
                    $promo_for_this_product_array = array();
                    $promo_filters = array_filter($promo_products, function($v) use ($cartdetail) { return $v->product_id == $cartdetail->product_id; });
                    // dd($promo_filters);
                    foreach($promo_filters as $promo_filter) {
                        $promo_for_this_product = new stdclass();
                        if($promo_filter->rule_type == 'product_discount_by_percentage') {
                            $discount = $promo_filter->discount_value * $original_price;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value * 100;
                        } elseif($promo_filter->rule_type == 'product_discount_by_value') {
                            $discount = $promo_filter->discount_value;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value;
                        } elseif ($used_product_coupon->issuedcoupon->rule_type == 'new_product_price') {
                            $discount = $promo_filter->discount_value;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value;
                        }
                        $promo_for_this_product->promotion_id = $promo_filter->promotion_id;
                        $promo_for_this_product->promotion_name = $promo_filter->promotion_name;
                        $promo_for_this_product->rule_type = $promo_filter->rule_type;
                        $promo_for_this_product->discount = $discount * $cartdetail->quantity;
                        $ammount_after_promo = $ammount_after_promo - $promo_for_this_product->discount;
                        $temp_price = $temp_price - $promo_for_this_product->discount;

                        // $promo_wo_tax = $discount / (1 + $product_vat_value);
                        if(!is_null($cartdetail->tax1)) {
                            $tax1 = $cartdetail->tax1->tax_value;
                            if(!is_null($cartdetail->tax2)) {
                                $tax2 = $cartdetail->tax2->tax_value;
                                if($cartdetail->tax2->tax_type == 'service') {
                                    $pwot  = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                                    $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                                    $tax1_total_value = $tax1_value * $cartdetail->quantity;
                                } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                    $tax1_value = ($discount / (1 + $tax1 + $tax2)) * $tax1;
                                    $tax1_total_value = $tax1_value * $cartdetail->quantity;
                                }
                            } else {
                                $tax1_value = ($discount / (1 + $tax1)) * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            }
                            foreach($taxes as $tax) {
                                if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                }
                            }
                        }
                        
                        if(!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            if(!is_null($cartdetail->tax1)) {
                                if($cartdetail->tax2->tax_type == 'service') {
                                    $tax2_value = ($discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                                    $tax2_total_value = $tax2_value * $cartdetail->quantity;
                                } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                    $tax2_value = ($discount / (1 + $tax1 + $tax2)) * $tax2;
                                    $tax2_total_value = $tax2_value * $cartdetail->quantity;
                                }
                            }
                            foreach($taxes as $tax) {
                                if($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                                }
                            }
                        }

                        if(!is_null($cartdetail->tax2)) {
                            if($cartdetail->tax2->tax_type == 'service') {
                                $promo_wo_tax = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                            } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                $promo_wo_tax = $discount / (1 + $tax1 + $tax2);
                            }
                        } else {
                            $promo_wo_tax = $discount / (1 + $tax1);
                        }

                        $promo_vat = ($discount - $promo_wo_tax) * $cartdetail->quantity;
                        $vat = $vat - $promo_vat;
                        $promo_wo_tax = $promo_wo_tax * $cartdetail->quantity;
                        $subtotal = $subtotal - $promo_for_this_product->discount;
                        $subtotal_wo_tax = $subtotal_wo_tax - $promo_wo_tax;
                        $promo_for_this_product_array[] = $promo_for_this_product;
                    }
                    // var_dump($promo_for_this_product_array);
                    $cartdetail->promo_for_this_product = $promo_for_this_product_array;

                    $coupon_filter = array();
                    foreach ($used_product_coupons as $used_product_coupon) {
                        // dd($used_product_coupon->cartdetail);
                        if ($used_product_coupon->cartdetail->product_variant_id == $cartdetail->product_variant_id) {
                            if ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_percentage') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value * $original_price;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value * 100;
                            } elseif ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_value') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                            } elseif ($used_product_coupon->issuedcoupon->rule_type == 'new_product_price') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                            }
                            $temp_price = $temp_price - $discount;
                            $used_product_coupon->discount = $discount;
                            $ammount_after_promo = $ammount_after_promo - $discount;

                            // $coupon_wo_tax = $discount / (1 + $product_vat_value);

                            if(!is_null($cartdetail->tax1)) {
                                $tax1 = $cartdetail->tax1->tax_value;
                                if(!is_null($cartdetail->tax2)) {
                                    $tax2 = $cartdetail->tax2->tax_value;
                                    if($cartdetail->tax2->tax_type == 'service') {
                                        $pwot  = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                                        $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                                        $tax1_total_value = $tax1_value;
                                    } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                        $tax1_value = ($discount / (1 + $tax1 + $tax2)) * $tax1;
                                        $tax1_total_value = $tax1_value;
                                    }
                                } else {
                                    $tax1_value = ($discount / (1 + $tax1)) * $tax1;
                                    $tax1_total_value = $tax1_value;
                                }
                                foreach($taxes as $tax) {
                                    if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                        $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                        $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                    }
                                }
                            }
                            
                            if(!is_null($cartdetail->tax2)) {
                                $tax2 = $cartdetail->tax2->tax_value;
                                if(!is_null($cartdetail->tax1)) {
                                    if($cartdetail->tax2->tax_type == 'service') {
                                        $tax2_value = ($discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                                        $tax2_total_value = $tax2_value;
                                    } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                        $tax2_value = ($discount / (1 + $tax1 + $tax2)) * $tax2;
                                        $tax2_total_value = $tax2_value;
                                    }
                                }
                                foreach($taxes as $tax) {
                                    if($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                        $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                        $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                                    }
                                }
                            }

                            if(!is_null($cartdetail->tax2)) {
                                if($cartdetail->tax2->tax_type == 'service') {
                                    $coupon_wo_tax = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                                } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                    $coupon_wo_tax = $discount / (1 + $tax1 + $tax2);
                                }
                            } else {
                                $coupon_wo_tax = $discount / (1 + $tax1);
                            }
                            $coupon_vat = ($discount - $coupon_wo_tax);
                            $vat = $vat - $coupon_vat;
                            $subtotal = $subtotal - $discount;
                            $subtotal_wo_tax = $subtotal_wo_tax - $coupon_wo_tax;
                            $coupon_filter[] = $used_product_coupon;
                        }
                    }
                    // dd($temp_price);
                    $cartdetail->coupon_for_this_product = $coupon_filter;
                    $cartdetail->original_price = $original_price;
                    $cartdetail->original_ammount = $original_ammount;
                    $cartdetail->ammount_after_promo = $ammount_after_promo;

                    if($cartdetail->attributeValue1['value']) {
                        $attributes[] = $cartdetail->attributeValue1['value'];
                    }
                    if($cartdetail->attributeValue2['value']) {
                        $attributes[] = $cartdetail->attributeValue2['value'];
                    }
                    if($cartdetail->attributeValue3['value']) {
                        $attributes[] = $cartdetail->attributeValue3['value'];
                    }
                    if($cartdetail->attributeValue4['value']) {
                        $attributes[] = $cartdetail->attributeValue4['value'];
                    }
                    if($cartdetail->attributeValue5['value']) {
                        $attributes[] = $cartdetail->attributeValue5['value'];
                    }
                    $cartdetail->attributes = $attributes;
                }
                if (count($cartdata->cartdetails) > 0 && $subtotal_wo_tax > 0) {
                    $cart_vat = $vat / $subtotal_wo_tax;
                } else {
                    $cart_vat = 0;
                }

                // dd($vat.' / '.$subtotal_wo_tax.' = '.$cart_vat);

                $subtotal_before_cart_promo_without_tax = $subtotal_wo_tax;
                $vat_before_cart_promo = $vat;
                $cartdiscounts = 0;
                $acquired_promo_carts = array();
                $discount_cart_promo = 0;
                $discount_cart_promo_wo_tax = 0;
                $discount_cart_coupon = 0;
                $cart_promo_taxes = 0;
                $subtotal_before_cart_promo = $subtotal;

                if (!empty($promo_carts)) {
                    foreach ($promo_carts as $promo_cart) {
                        if ($subtotal >= $promo_cart->promotionrule->rule_value) {
                            if ($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') {
                                $discount = $subtotal * $promo_cart->promotionrule->discount_value;
                                $promo_cart->disc_val_str = '-'.($promo_cart->promotionrule->discount_value * 100).'%';
                                $promo_cart->disc_val = '-'.($subtotal * $promo_cart->promotionrule->discount_value);
                            } elseif ($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') {
                                $discount = $promo_cart->promotionrule->discount_value;
                                $promo_cart->disc_val_str = '-'.$promo_cart->promotionrule->discount_value + 0;
                                $promo_cart->disc_val = '-'.$promo_cart->promotionrule->discount_value + 0;
                            }

                            $cart_promo_wo_tax = $discount / (1 + $cart_vat);
                            $cart_promo_tax = $discount - $cart_promo_wo_tax;
                            $cart_promo_taxes = $cart_promo_taxes + $cart_promo_tax;
                            
                            foreach ($taxes as $tax) {
                                if (!empty($tax->total_tax)) {
                                    $tax_reduction = ($tax->total_tax_before_cart_promo / $vat_before_cart_promo) * $cart_promo_tax;
                                    $tax->total_tax = $tax->total_tax - $tax_reduction;
                                }
                            }

                            $discount_cart_promo = $discount_cart_promo + $discount;
                            $discount_cart_promo_wo_tax = $discount_cart_promo_wo_tax + $cart_promo_wo_tax;
                            $acquired_promo_carts[] = $promo_cart;

                        }
                    }
                    
                }

                $coupon_carts = Coupon::join('promotion_rules', function($q) use($subtotal)
                {
                    $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.discount_object_type', '=', 'cash_rebate')->where('promotion_rules.coupon_redeem_rule_value', '<=', $subtotal);
                })->excludeDeleted()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function($q) use ($retailer)
                {
                    $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                })
                ->whereHas('issuedcoupons',function($q) use($user)
                {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
                })->with(array('issuedcoupons' => function($q) use($user)
                {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
                }))
                ->get();

                $available_coupon_carts = array();
                $cart_discount_by_percentage_counter = 0;
                $discount_cart_coupon = 0;
                $discount_cart_coupon_wo_tax = 0;
                $total_cart_coupon_discount = 0;
                $cart_coupon_taxes = 0;
                $acquired_coupon_carts = array();
                if(!empty($used_cart_coupons)) {
                    foreach($used_cart_coupons as $used_cart_coupon) {
                        if(!empty($used_cart_coupon->issuedcoupon->coupon_redeem_rule_value)) {
                            if($subtotal >= $used_cart_coupon->issuedcoupon->coupon_redeem_rule_value) {
                                if($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                                    $used_cart_coupon->disc_val_str = '-'.($used_cart_coupon->issuedcoupon->discount_value * 100).'%';
                                    $used_cart_coupon->disc_val = '-'.($used_cart_coupon->issuedcoupon->discount_value * $subtotal);
                                    $discount = $subtotal * $used_cart_coupon->issuedcoupon->discount_value;
                                    $cart_discount_by_percentage_counter++;
                                } elseif($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
                                    $used_cart_coupon->disc_val_str = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                    $used_cart_coupon->disc_val = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                    $discount = $used_cart_coupon->issuedcoupon->discount_value;
                                }

                                $cart_coupon_wo_tax = $discount / (1 + $cart_vat);
                                $cart_coupon_tax = $discount - $cart_coupon_wo_tax;

                                foreach ($taxes as $tax) {
                                    if (!empty($tax->total_tax)) {
                                        $tax_reduction = ($tax->total_tax_before_cart_promo / $vat_before_cart_promo) * $cart_coupon_tax;
                                        $tax->total_tax = $tax->total_tax - $tax_reduction;
                                    }
                                }
                                
                                $cart_coupon_taxes = $cart_coupon_taxes + $cart_coupon_tax;
                                $discount_cart_coupon = $discount_cart_coupon + $discount;
                                $discount_cart_coupon_wo_tax = $discount_cart_coupon_wo_tax + $cart_coupon_wo_tax;

                                $total_cart_coupon_discount = $total_cart_coupon_discount + $discount;
                                $acquired_coupon_carts[] = $used_cart_coupon;
                            } else {
                                $this->beginTransaction();
                                $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $used_cart_coupon->issued_coupon_id)->first();
                                $issuedcoupon->makeActive();
                                $issuedcoupon->save();
                                $used_cart_coupon->delete(TRUE);
                                $this->commit();
                            }
                        }
                    }
                }

                if(!empty($coupon_carts)) {
                    foreach($coupon_carts as $coupon_cart) {
                        if($subtotal >= $coupon_cart->coupon_redeem_rule_value) {
                            if($coupon_cart->rule_type == 'cart_discount_by_percentage') {
                                if($cart_discount_by_percentage_counter == 0) { // prevent more than one cart_discount_by_percentage
                                    $discount = $subtotal * $coupon_cart->discount_value;
                                    $cartdiscounts = $cartdiscounts + $discount;
                                    $coupon_cart->disc_val_str = '-'.($coupon_cart->discount_value * 100).'%';
                                    $coupon_cart->disc_val = '-'.($subtotal * $coupon_cart->discount_value);
                                    $available_coupon_carts[] = $coupon_cart;
                                    $cart_discount_by_percentage_counter++;
                                }
                            } elseif ($coupon_cart->rule_type == 'cart_discount_by_value') {
                                $discount = $coupon_cart->discount_value;
                                $cartdiscounts = $cartdiscounts + $discount;
                                $coupon_cart->disc_val_str = '-'.$coupon_cart->discount_value + 0;
                                $coupon_cart->disc_val = '-'.$coupon_cart->discount_value + 0;
                                $available_coupon_carts[] = $coupon_cart;
                            }
                        } else {
                            $coupon_cart->disc_val = $coupon_cart->rule_value;
                        }
                    }
                }

                $subtotal = $subtotal - $discount_cart_promo - $discount_cart_coupon;
                $subtotal_wo_tax = $subtotal_wo_tax - $discount_cart_promo_wo_tax - $discount_cart_coupon_wo_tax;
                $vat = $vat - $cart_promo_taxes - $cart_coupon_taxes;
                // dd($cart_coupon_taxes);

                $cartsummary = new stdclass();
                $cartsummary->vat = round($vat, 2);
                $cartsummary->total_to_pay = round($subtotal, 2);
                $cartsummary->subtotal_wo_tax = $subtotal_wo_tax; 
                $cartsummary->acquired_promo_carts = $acquired_promo_carts;
                $cartsummary->used_cart_coupons = $acquired_coupon_carts;
                $cartsummary->available_coupon_carts = $available_coupon_carts;
                $cartsummary->subtotal_before_cart_promo = round($subtotal_before_cart_promo, 2);
                $cartsummary->taxes = $taxes;
                $cartsummary->subtotal_before_cart_promo_without_tax = $subtotal_before_cart_promo_without_tax;
                $cartsummary->vat_before_cart_promo = $vat_before_cart_promo;
                $cartdata->cartsummary = $cartsummary;
                // $cartdata->attributes = $attributes;
            } else {
                foreach ($cartdata->cartdetails as $cartdetail) {
                    $attributes = array();
                    $product_vat_value = 0;
                    $original_price = $cartdetail->variant->price;
                    $subtotal_wo_tax = $subtotal_wo_tax + ($original_price * $cartdetail->quantity);
                    $original_ammount = $original_price * $cartdetail->quantity;

                    $available_product_coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                            inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                            WHERE 
                                ic.expired_date >= NOW() 
                                AND p.merchant_id = :merchantid 
                                AND prr.retailer_id = :retailerid 
                                AND ic.user_id = :userid 
                                AND prod.product_id = :productid 
                                
                            '), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $cartdetail->product_id));
 
                    $cartdetail->available_product_coupons = count($available_product_coupons);

                    if (!is_null($cartdetail->tax1)) {
                        $tax1 = $cartdetail->tax1->tax_value;
                        if (!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $pwt = $original_price + ($original_price * $tax2) ;
                                $tax1_value = $pwt * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                $tax1_value = $original_price * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            }
                        } else {
                            $tax1_value = $original_price * $tax1;
                            $tax1_total_value = $tax1_value * $cartdetail->quantity;
                        }
                        foreach ($taxes as $tax) {
                            if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax + $tax1_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax1_total_value;
                            }
                        }
                    } else {
                        $tax1 = 0;
                    }

                    if (!is_null($cartdetail->tax2)) {
                        $tax2 = $cartdetail->tax2->tax_value;    
                        $tax2_value = $original_price * $tax2;
                        $tax2_total_value = $tax2_value * $cartdetail->quantity;
                        foreach ($taxes as $tax) {
                            if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax + $tax2_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax2_total_value;
                            }
                        }
                    } else {
                        $tax2 = 0;
                    }

                    if(!is_null($cartdetail->tax2)) {
                        if($cartdetail->tax2->tax_type == 'service') {
                            $product_price_with_tax = $original_price * (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                        } elseif($cartdetail->tax2->tax_type == 'luxury') {
                            $product_price_with_tax = $original_price * (1 + $tax1 + $tax2);
                        }
                    } else {
                        $product_price_with_tax = $original_price * (1 + $tax1);
                    }

                    $product_vat = ($product_price_with_tax - $original_price) * $cartdetail->quantity;
                    $vat = $vat + $product_vat;
                    
                    $product_price_with_tax = $product_price_with_tax * $cartdetail->quantity;
                    $ammount_after_promo = $product_price_with_tax;
                    $subtotal = $subtotal + $product_price_with_tax;
                    $temp_price = $original_ammount;

                    $promo_for_this_product_array = array();
                    $promo_filters = array_filter($promo_products, function($v) use ($cartdetail) { return $v->product_id == $cartdetail->product_id; });
                    // dd($promo_filters);
                    foreach($promo_filters as $promo_filter) {
                        $promo_for_this_product = new stdclass();
                        if($promo_filter->rule_type == 'product_discount_by_percentage') {
                            $discount = $promo_filter->discount_value * $original_price;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value * 100;
                        } elseif($promo_filter->rule_type == 'product_discount_by_value') {
                            $discount = $promo_filter->discount_value;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value;
                        } elseif ($used_product_coupon->issuedcoupon->rule_type == 'new_product_price') {
                            $discount = $promo_filter->discount_value;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value;
                        }
                        $promo_for_this_product->promotion_id = $promo_filter->promotion_id;
                        $promo_for_this_product->promotion_name = $promo_filter->promotion_name;
                        $promo_for_this_product->rule_type = $promo_filter->rule_type;
                        $promo_for_this_product->discount = $discount * $cartdetail->quantity;
                        $ammount_after_promo = $ammount_after_promo - $promo_for_this_product->discount;
                        $temp_price = $temp_price - $promo_for_this_product->discount;

                        $promo_wo_tax = $discount / (1 + $product_vat_value);
                        if(!is_null($cartdetail->tax1)) {
                            $tax1 = $cartdetail->tax1->tax_value;
                            if(!is_null($cartdetail->tax2)) {
                                $tax2 = $cartdetail->tax2->tax_value;
                                if ($cartdetail->tax2->tax_type == 'service') {
                                    $pwt = $discount;
                                    $tax1_value = $pwt * $tax1;
                                    $tax1_total_value = $tax1_value * $cartdetail->quantity;
                                } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                    $tax1_value = $discount * $tax1;
                                    $tax1_total_value = $tax1_value * $cartdetail->quantity;
                                }
                            } else {
                                $tax1_value = $discount * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            }
                            foreach($taxes as $tax) {
                                if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                }
                            }
                        }
                        
                        if(!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;    
                            $tax2_value = $discount * $tax2;
                            $tax2_total_value = $tax2_value * $cartdetail->quantity;
                            
                            foreach ($taxes as $tax) {
                                if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                                }
                            }
                        }

                        if(!is_null($cartdetail->tax2)) {
                            if($cartdetail->tax2->tax_type == 'service') {
                                $promo_with_tax = $discount * (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                            } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                $promo_with_tax = $discount * (1 + $tax1 + $tax2);
                            }
                        } else {
                            $promo_with_tax = $discount * (1 + $tax1);
                        }



                        $promo_vat = ($promo_with_tax - $discount) * $cartdetail->quantity;
                        // $promo_vat = ($discount * $cartdetail->quantity);
                        
                        
                        $vat = $vat - $promo_vat;
                        $promo_with_tax = $promo_with_tax * $cartdetail->quantity;
                        $subtotal = $subtotal - $promo_with_tax;
                        $subtotal_wo_tax = $subtotal_wo_tax - ($discount * $cartdetail->quantity);
                        $promo_for_this_product_array[] = $promo_for_this_product;
                    }
                    
                    $cartdetail->promo_for_this_product = $promo_for_this_product_array;

                    $coupon_filter = array();
                    foreach($used_product_coupons as $used_product_coupon) {
                        // dd($used_product_coupon->cartdetail);
                        if($used_product_coupon->cartdetail->product_variant_id == $cartdetail->product_variant_id) {
                            if($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_percentage') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value * $original_price;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value * 100;
                            } elseif($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_value') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                            } elseif ($used_product_coupon->issuedcoupon->rule_type == 'new_product_price') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                            }
                            $temp_price = $temp_price - $discount;
                            $used_product_coupon->discount = $discount;
                            $ammount_after_promo = $ammount_after_promo - $discount;
                            // $coupon_wo_tax = $discount / (1 + $product_vat_value);

                            if(!is_null($cartdetail->tax1)) {
                                $tax1 = $cartdetail->tax1->tax_value;
                                if(!is_null($cartdetail->tax2)) {
                                    $tax2 = $cartdetail->tax2->tax_value;
                                    if ($cartdetail->tax2->tax_type == 'service') {
                                        $pwt = $discount + ($discount * $tax2) ;
                                        $tax1_value = $pwt * $tax1;
                                        $tax1_total_value = $tax1_value;
                                    } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                        $tax1_value = $discount * $tax1;
                                        $tax1_total_value = $tax1_value;
                                    }
                                } else {
                                    $tax1_value = $discount * $tax1;
                                    $tax1_total_value = $tax1_value;
                                }
                                foreach($taxes as $tax) {
                                    if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                        $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                        $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                    }
                                }
                            }
                            
                            if(!is_null($cartdetail->tax2)) {
                                $tax2 = $cartdetail->tax2->tax_value;    
                                $tax2_value = $discount * $tax2;
                                $tax2_total_value = $tax2_value;
                                
                                foreach ($taxes as $tax) {
                                    if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                        $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                        $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                                    }
                                }
                            }

                            if(!is_null($cartdetail->tax2)) {
                                if($cartdetail->tax2->tax_type == 'service') {
                                    $coupon_with_tax = $discount * (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                                } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                    $coupon_with_tax = $discount * (1 + $tax1 + $tax2);
                                }
                            } else {
                                $coupon_with_tax = $discount * (1 + $tax1);
                            }
                            // $coupon_vat = ($discount - $coupon_wo_tax);
                            // $vat = $vat - $coupon_vat;
                            // $subtotal = $subtotal - $discount;
                            // $subtotal_wo_tax = $subtotal_wo_tax - $coupon_wo_tax;
                            $coupon_vat = ($coupon_with_tax - $discount);
                            $vat = $vat - $coupon_vat;
                            $subtotal = $subtotal - $coupon_with_tax;
                            $subtotal_wo_tax = $subtotal_wo_tax - $discount;
                            $coupon_filter[] = $used_product_coupon;
                        }
                    }
                    // dd($coupon_filter[1]);
                    $cartdetail->coupon_for_this_product = $coupon_filter;

                    $cartdetail->original_price = $original_price;
                    $cartdetail->original_ammount = $original_ammount;
                    $cartdetail->ammount_after_promo = $ammount_after_promo;

                    if($cartdetail->attributeValue1['value']) {
                        $attributes[] = $cartdetail->attributeValue1['value'];
                    }
                    if($cartdetail->attributeValue2['value']) {
                        $attributes[] = $cartdetail->attributeValue2['value'];
                    }
                    if($cartdetail->attributeValue3['value']) {
                        $attributes[] = $cartdetail->attributeValue3['value'];
                    }
                    if($cartdetail->attributeValue4['value']) {
                        $attributes[] = $cartdetail->attributeValue4['value'];
                    }
                    if($cartdetail->attributeValue5['value']) {
                        $attributes[] = $cartdetail->attributeValue5['value'];
                    }
                    $cartdetail->attributes = $attributes;
                }

                if (count($cartdata->cartdetails) > 0 && $subtotal_wo_tax > 0) {
                    $cart_vat = $vat / $subtotal_wo_tax;
                } else {
                    $cart_vat = 0;
                }


                $subtotal_before_cart_promo_without_tax = $subtotal_wo_tax;
                $vat_before_cart_promo = $vat;
                $cartdiscounts = 0;
                $acquired_promo_carts = array();
                $discount_cart_promo = 0;
                $discount_cart_promo_with_tax = 0;
                $discount_cart_coupon = 0;
                $cart_promo_taxes = 0;
                $subtotal_before_cart_promo = $subtotal;

                if (!empty($promo_carts)) {
                    foreach ($promo_carts as $promo_cart) {
                        if ($subtotal_before_cart_promo_without_tax >= $promo_cart->promotionrule->rule_value) {
                            if ($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') {
                                $discount = $subtotal_before_cart_promo_without_tax * $promo_cart->promotionrule->discount_value;
                                $promo_cart->disc_val_str = '-'.($promo_cart->promotionrule->discount_value * 100).'%';
                                $promo_cart->disc_val = '-'.($subtotal_before_cart_promo_without_tax * $promo_cart->promotionrule->discount_value);
                            } elseif ($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') {
                                $discount = $promo_cart->promotionrule->discount_value;
                                $promo_cart->disc_val_str = '-'.$promo_cart->promotionrule->discount_value + 0;
                                $promo_cart->disc_val = '-'.$promo_cart->promotionrule->discount_value + 0;
                            }

                            $cart_promo_with_tax = $discount * (1 + $cart_vat);
                            
                            // $cart_promo_tax = $cart_promo_with_tax - $discount;
                            
                            $cart_promo_tax = $discount / $subtotal_wo_tax * $vat_before_cart_promo;
                            $cart_promo_taxes = $cart_promo_taxes + $cart_promo_tax;
                            
                            foreach ($taxes as $tax) {
                                if (!empty($tax->total_tax)) {
                                    // $tax_reduction = ($tax->total_tax_before_cart_promo / $vat_before_cart_promo) * $cart_promo_tax;
                                    $tax_reduction = ($discount / $subtotal_wo_tax) * $cart_promo_tax;
                                    $tax->total_tax = $tax->total_tax - $tax_reduction;
                                }
                            }

                            $discount_cart_promo = $discount_cart_promo + $discount;
                            $discount_cart_promo_with_tax = $discount_cart_promo_with_tax - $cart_promo_with_tax;
                            $acquired_promo_carts[] = $promo_cart;
                            // dd($cart_promo_with_tax);
                        }
                    }
                    
                }

                $coupon_carts = Coupon::join('promotion_rules', function($q) use($subtotal_before_cart_promo_without_tax)
                {
                    $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.discount_object_type', '=', 'cash_rebate')->where('promotion_rules.coupon_redeem_rule_value', '<=', $subtotal_before_cart_promo_without_tax);
                })->excludeDeleted()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function($q) use ($retailer)
                {
                    $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                })
                ->whereHas('issuedcoupons',function($q) use($user)
                {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
                })->with(array('issuedcoupons' => function($q) use($user)
                {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
                }))
                ->get();

                $available_coupon_carts = array();
                $cart_discount_by_percentage_counter = 0;
                $discount_cart_coupon = 0;
                $discount_cart_coupon_with_tax = 0;
                $total_cart_coupon_discount = 0;
                $cart_coupon_taxes = 0;
                $acquired_coupon_carts = array();
                if(!empty($used_cart_coupons)) {
                    foreach($used_cart_coupons as $used_cart_coupon) {
                        if(!empty($used_cart_coupon->issuedcoupon->coupon_redeem_rule_value)) {
                            if($subtotal_before_cart_promo_without_tax >= $used_cart_coupon->issuedcoupon->coupon_redeem_rule_value) {
                                if($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                                    $used_cart_coupon->disc_val_str = '-'.($used_cart_coupon->issuedcoupon->discount_value * 100).'%';
                                    $used_cart_coupon->disc_val = '-'.($used_cart_coupon->issuedcoupon->discount_value * $subtotal_before_cart_promo_without_tax);
                                    $discount = $subtotal_before_cart_promo_without_tax * $used_cart_coupon->issuedcoupon->discount_value;
                                    $cart_discount_by_percentage_counter++;
                                } elseif($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
                                    $used_cart_coupon->disc_val_str = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                    $used_cart_coupon->disc_val = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                    $discount = $used_cart_coupon->issuedcoupon->discount_value;
                                }

                                $cart_coupon_with_tax = $discount * (1 + $cart_vat);
                                // $cart_coupon_tax = $cart_coupon_with_tax - $discount;
                                $cart_coupon_tax = $discount / $subtotal_wo_tax * $vat_before_cart_promo;
                                $cart_coupon_taxes = $cart_coupon_taxes + $cart_coupon_tax;

                                foreach ($taxes as $tax) {
                                    if (!empty($tax->total_tax)) {
                                        $tax_reduction = ($tax->total_tax_before_cart_promo / $vat_before_cart_promo) * $cart_coupon_tax;
                                        $tax->total_tax = $tax->total_tax - $tax_reduction;
                                    }
                                }
                                
                                $discount_cart_coupon = $discount_cart_coupon + $discount;
                                $discount_cart_coupon_with_tax = $discount_cart_coupon_with_tax - $cart_coupon_with_tax;

                                $total_cart_coupon_discount = $total_cart_coupon_discount + $discount;
                                $acquired_coupon_carts[] = $used_cart_coupon;
                            } else {
                                $this->beginTransaction();
                                $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $used_cart_coupon->issued_coupon_id)->first();
                                $issuedcoupon->makeActive();
                                $issuedcoupon->save();
                                $used_cart_coupon->delete(TRUE);
                                $this->commit();
                            }
                        }
                    }
                }
                
                if (!empty($coupon_carts)) {
                    foreach ($coupon_carts as $coupon_cart) {
                        if ($subtotal_before_cart_promo_without_tax >= $coupon_cart->coupon_redeem_rule_value) {
                            if ($coupon_cart->rule_type == 'cart_discount_by_percentage') {
                                if ($cart_discount_by_percentage_counter == 0) { // prevent more than one cart_discount_by_percentage
                                    $discount = $subtotal_before_cart_promo_without_tax * $coupon_cart->discount_value;
                                    $cartdiscounts = $cartdiscounts + $discount;
                                    $coupon_cart->disc_val_str = '-'.($coupon_cart->discount_value * 100).'%';
                                    $coupon_cart->disc_val = '-'.($subtotal_before_cart_promo_without_tax * $coupon_cart->discount_value);
                                    $available_coupon_carts[] = $coupon_cart;
                                    $cart_discount_by_percentage_counter++;
                                }
                            } elseif ($coupon_cart->rule_type == 'cart_discount_by_value') {
                                $discount = $coupon_cart->discount_value;
                                $cartdiscounts = $cartdiscounts + $discount;
                                $coupon_cart->disc_val_str = '-'.$coupon_cart->discount_value + 0;
                                $coupon_cart->disc_val = '-'.$coupon_cart->discount_value + 0;
                                $available_coupon_carts[] = $coupon_cart;
                            }
                        } else {
                            $coupon_cart->disc_val = $coupon_cart->rule_value;
                        }
                    }
                }
                // dd($discount_cart_coupon);
                $subtotal_wo_tax = $subtotal_wo_tax - $discount_cart_promo - $discount_cart_coupon;
                $subtotal = $subtotal + $discount_cart_promo_with_tax + $discount_cart_coupon_with_tax;
                $vat = $vat - $cart_promo_taxes - $cart_coupon_taxes;
                // dd($cart_coupon_taxes);
                
                $cartsummary = new stdclass();
                $cartsummary->vat = round($vat, 2);
                $cartsummary->total_to_pay = round($subtotal, 2);
                $cartsummary->subtotal_wo_tax = $subtotal_wo_tax; 
                $cartsummary->acquired_promo_carts = $acquired_promo_carts;
                $cartsummary->used_cart_coupons = $acquired_coupon_carts;
                $cartsummary->available_coupon_carts = $available_coupon_carts;
                $cartsummary->subtotal_before_cart_promo = round($subtotal_before_cart_promo, 2);
                $cartsummary->taxes = $taxes;
                $cartsummary->subtotal_before_cart_promo_without_tax = $subtotal_before_cart_promo_without_tax;
                $cartsummary->vat_before_cart_promo = $vat_before_cart_promo;
                $cartdata->cartsummary = $cartsummary;
            }
            // dd($vat);
            // print_r($cartdata);
            $activityPageNotes = sprintf('Page viewed: %s', 'Cart');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_cart')
                            ->setActivityNameLong('View (Cart Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.cart', array('page_title'=>Lang::get('mobileci.page_title.cart'), 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata));
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Cart');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_cart')
                            ->setActivityNameLong('View (Cart Page) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function getTransferCartView()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartitems = $this->getCartForToolbar();

            $cartdata = $this->getCartData();

            $activityPageNotes = sprintf('Page viewed: %s', 'Transfer Cart');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_transfer_cart')
                            ->setActivityNameLong('View (Transfer Cart Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.transfer-cart', array('page_title'=>Lang::get('mobileci.page_title.transfercart'), 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata));
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Transfer Cart');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_transfer_cart')
                            ->setActivityNameLong('View (Transfer Cart Page) Failed')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
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
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();
            
            $cartitems = $this->getCartForToolbar();

            $cart = Cart::where('status', 'active')->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->first();
            if (is_null($cart)) {
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
            })->get();

            $used_cart_coupons = CartCoupon::with(array('cart', 'issuedcoupon' => function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id)
                ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');
            }))
            ->whereHas('cart', function($q) use($cartdata)
            {
                $q->where('cart_coupons.object_type', '=', 'cart')
                ->where('cart_coupons.object_id', '=', $cartdata->cart->cart_id);
            })
            ->where('cart_coupons.object_type', '=', 'cart')->get();

            $subtotal = 0;
            $subtotal_wo_tax = 0;
            $vat = 0;
            $total = 0;

            $taxes = \MerchantTax::excludeDeleted()->where('merchant_id', $retailer->parent_id)->get();
            
            $vat_included = $retailer->parent->vat_included;

            if($vat_included === 'yes') {
                foreach($cartdata->cartdetails as $cartdetail) {
                    $attributes = array();
                    $product_vat_value = 0;
                    $original_price = $cartdetail->variant->price;
                    $original_ammount = $original_price * $cartdetail->quantity;
                    $ammount_after_promo = $original_ammount;
                    $product_price_wo_tax = $original_price;

                    $available_product_coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                            inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                            WHERE 
                                ic.expired_date >= NOW() 
                                AND p.merchant_id = :merchantid 
                                AND prr.retailer_id = :retailerid 
                                AND ic.user_id = :userid 
                                AND prod.product_id = :productid 
                                
                            '), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $cartdetail->product_id));
 
                    $cartdetail->available_product_coupons = count($available_product_coupons);

                    if(!is_null($cartdetail->tax1)) {
                        $tax1 = $cartdetail->tax1->tax_value;
                        if(!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            if($cartdetail->tax2->tax_type == 'service') {
                                $pwot  = $original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                                $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                $tax1_value = ($original_price / (1 + $tax1 + $tax2)) * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            }
                        } else {
                            $tax1_value = ($original_price / (1 + $tax1)) * $tax1;
                            $tax1_total_value = $tax1_value * $cartdetail->quantity;
                        }
                        foreach($taxes as $tax) {
                            if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax + $tax1_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax1_total_value;
                            }
                        }
                    } else {
                        $tax1 = 0;
                    }
                    
                    if(!is_null($cartdetail->tax2)) {
                        $tax2 = $cartdetail->tax2->tax_value;
                        if(!is_null($cartdetail->tax1)) {
                            if($cartdetail->tax2->tax_type == 'service') {
                                $tax2_value = ($original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                                $tax2_total_value = $tax2_value * $cartdetail->quantity;
                            } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                $tax2_value = ($original_price / (1 + $tax1 + $tax2)) * $tax2;
                                $tax2_total_value = $tax2_value * $cartdetail->quantity;
                            }
                        }
                        foreach($taxes as $tax) {
                            if($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax + $tax2_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax2_total_value;
                            }
                        }
                    } else {
                        $tax2 = 0;
                    }

                    // $product_price_wo_tax = $original_price / (1 + $product_vat_value);
                    if(!is_null($cartdetail->tax2)) {
                        if($cartdetail->tax2->tax_type == 'service') {
                            $product_price_wo_tax = $original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                        } elseif($cartdetail->tax2->tax_type == 'luxury') {
                            $product_price_wo_tax = $original_price / (1 + $tax1 + $tax2);
                        }
                    } else {
                        $product_price_wo_tax = $original_price / (1 + $tax1);
                    }
                    // dd($product_price_wo_tax);
                    $product_vat = ($original_price - $product_price_wo_tax) * $cartdetail->quantity;
                    $vat = $vat + $product_vat;
                    $product_price_wo_tax = $product_price_wo_tax * $cartdetail->quantity;
                    $subtotal = $subtotal + $original_ammount;
                    $subtotal_wo_tax = $subtotal_wo_tax + $product_price_wo_tax;

                    $temp_price = $original_ammount;
                    $promo_for_this_product_array = array();
                    $promo_filters = array_filter($promo_products, function($v) use ($cartdetail) { return $v->product_id == $cartdetail->product_id; });
                    // dd($promo_filters);
                    foreach($promo_filters as $promo_filter) {
                        $promo_for_this_product = new stdclass();
                        if ($promo_filter->rule_type == 'product_discount_by_percentage') {
                            $discount = $promo_filter->discount_value * $original_price;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value * 100;
                        } elseif ($promo_filter->rule_type == 'product_discount_by_value') {
                            $discount = $promo_filter->discount_value;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value;
                        } elseif ($used_product_coupon->issuedcoupon->rule_type == 'new_product_price') {
                            $discount = $promo_filter->discount_value;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value;
                        }
                        $promo_for_this_product->promotion_id = $promo_filter->promotion_id;
                        $promo_for_this_product->promotion_name = $promo_filter->promotion_name;
                        $promo_for_this_product->rule_type = $promo_filter->rule_type;
                        $promo_for_this_product->discount = $discount * $cartdetail->quantity;
                        $ammount_after_promo = $ammount_after_promo - $promo_for_this_product->discount;
                        $temp_price = $temp_price - $promo_for_this_product->discount;

                        // $promo_wo_tax = $discount / (1 + $product_vat_value);
                        if(!is_null($cartdetail->tax1)) {
                            $tax1 = $cartdetail->tax1->tax_value;
                            if(!is_null($cartdetail->tax2)) {
                                $tax2 = $cartdetail->tax2->tax_value;
                                if($cartdetail->tax2->tax_type == 'service') {
                                    $pwot  = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                                    $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                                    $tax1_total_value = $tax1_value * $cartdetail->quantity;
                                } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                    $tax1_value = ($discount / (1 + $tax1 + $tax2)) * $tax1;
                                    $tax1_total_value = $tax1_value * $cartdetail->quantity;
                                }
                            } else {
                                $tax1_value = ($discount / (1 + $tax1)) * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            }
                            foreach($taxes as $tax) {
                                if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                }
                            }
                        }
                        
                        if(!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            if(!is_null($cartdetail->tax1)) {
                                if($cartdetail->tax2->tax_type == 'service') {
                                    $tax2_value = ($discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                                    $tax2_total_value = $tax2_value * $cartdetail->quantity;
                                } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                    $tax2_value = ($discount / (1 + $tax1 + $tax2)) * $tax2;
                                    $tax2_total_value = $tax2_value * $cartdetail->quantity;
                                }
                            }
                            foreach($taxes as $tax) {
                                if($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                                }
                            }
                        }

                        if(!is_null($cartdetail->tax2)) {
                            if($cartdetail->tax2->tax_type == 'service') {
                                $promo_wo_tax = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                            } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                $promo_wo_tax = $discount / (1 + $tax1 + $tax2);
                            }
                        } else {
                            $promo_wo_tax = $discount / (1 + $tax1);
                        }

                        $promo_vat = ($discount - $promo_wo_tax) * $cartdetail->quantity;
                        $vat = $vat - $promo_vat;
                        $promo_wo_tax = $promo_wo_tax * $cartdetail->quantity;
                        $subtotal = $subtotal - $promo_for_this_product->discount;
                        $subtotal_wo_tax = $subtotal_wo_tax - $promo_wo_tax;
                        $promo_for_this_product_array[] = $promo_for_this_product;
                    }
                    // var_dump($promo_for_this_product_array);
                    $cartdetail->promo_for_this_product = $promo_for_this_product_array;

                    $coupon_filter = array();
                    foreach ($used_product_coupons as $used_product_coupon) {
                        // dd($used_product_coupon->cartdetail);
                        if ($used_product_coupon->cartdetail->product_variant_id == $cartdetail->product_variant_id) {
                            if ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_percentage') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value * $original_price;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value * 100;
                            } elseif ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_value') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                            } elseif ($used_product_coupon->issuedcoupon->rule_type == 'new_product_price') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                            }
                            $temp_price = $temp_price - $discount;
                            $used_product_coupon->discount = $discount;
                            $ammount_after_promo = $ammount_after_promo - $discount;

                            // $coupon_wo_tax = $discount / (1 + $product_vat_value);

                            if(!is_null($cartdetail->tax1)) {
                                $tax1 = $cartdetail->tax1->tax_value;
                                if(!is_null($cartdetail->tax2)) {
                                    $tax2 = $cartdetail->tax2->tax_value;
                                    if($cartdetail->tax2->tax_type == 'service') {
                                        $pwot  = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                                        $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                                        $tax1_total_value = $tax1_value;
                                    } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                        $tax1_value = ($discount / (1 + $tax1 + $tax2)) * $tax1;
                                        $tax1_total_value = $tax1_value;
                                    }
                                } else {
                                    $tax1_value = ($discount / (1 + $tax1)) * $tax1;
                                    $tax1_total_value = $tax1_value;
                                }
                                foreach($taxes as $tax) {
                                    if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                        $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                        $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                    }
                                }
                            }
                            
                            if(!is_null($cartdetail->tax2)) {
                                $tax2 = $cartdetail->tax2->tax_value;
                                if(!is_null($cartdetail->tax1)) {
                                    if($cartdetail->tax2->tax_type == 'service') {
                                        $tax2_value = ($discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                                        $tax2_total_value = $tax2_value;
                                    } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                        $tax2_value = ($discount / (1 + $tax1 + $tax2)) * $tax2;
                                        $tax2_total_value = $tax2_value;
                                    }
                                }
                                foreach($taxes as $tax) {
                                    if($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                        $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                        $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                                    }
                                }
                            }

                            if(!is_null($cartdetail->tax2)) {
                                if($cartdetail->tax2->tax_type == 'service') {
                                    $coupon_wo_tax = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                                } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                    $coupon_wo_tax = $discount / (1 + $tax1 + $tax2);
                                }
                            } else {
                                $coupon_wo_tax = $discount / (1 + $tax1);
                            }
                            $coupon_vat = ($discount - $coupon_wo_tax);
                            $vat = $vat - $coupon_vat;
                            $subtotal = $subtotal - $discount;
                            $subtotal_wo_tax = $subtotal_wo_tax - $coupon_wo_tax;
                            $coupon_filter[] = $used_product_coupon;
                        }
                    }
                    // dd($temp_price);
                    $cartdetail->coupon_for_this_product = $coupon_filter;
                    $cartdetail->original_price = $original_price;
                    $cartdetail->original_ammount = $original_ammount;
                    $cartdetail->ammount_after_promo = $ammount_after_promo;

                    if($cartdetail->attributeValue1['value']) {
                        $attributes[] = $cartdetail->attributeValue1['value'];
                    }
                    if($cartdetail->attributeValue2['value']) {
                        $attributes[] = $cartdetail->attributeValue2['value'];
                    }
                    if($cartdetail->attributeValue3['value']) {
                        $attributes[] = $cartdetail->attributeValue3['value'];
                    }
                    if($cartdetail->attributeValue4['value']) {
                        $attributes[] = $cartdetail->attributeValue4['value'];
                    }
                    if($cartdetail->attributeValue5['value']) {
                        $attributes[] = $cartdetail->attributeValue5['value'];
                    }
                    $cartdetail->attributes = $attributes;
                }
                if (count($cartdata->cartdetails) > 0 && $subtotal_wo_tax > 0) {
                    $cart_vat = $vat / $subtotal_wo_tax;
                } else {
                    $cart_vat = 0;
                }

                // dd($vat.' / '.$subtotal_wo_tax.' = '.$cart_vat);

                $subtotal_before_cart_promo_without_tax = $subtotal_wo_tax;
                $vat_before_cart_promo = $vat;
                $cartdiscounts = 0;
                $acquired_promo_carts = array();
                $discount_cart_promo = 0;
                $discount_cart_promo_wo_tax = 0;
                $discount_cart_coupon = 0;
                $cart_promo_taxes = 0;
                $subtotal_before_cart_promo = $subtotal;

                if (!empty($promo_carts)) {
                    foreach ($promo_carts as $promo_cart) {
                        if ($subtotal >= $promo_cart->promotionrule->rule_value) {
                            if ($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') {
                                $discount = $subtotal * $promo_cart->promotionrule->discount_value;
                                $promo_cart->disc_val_str = '-'.($promo_cart->promotionrule->discount_value * 100).'%';
                                $promo_cart->disc_val = '-'.($subtotal * $promo_cart->promotionrule->discount_value);
                            } elseif ($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') {
                                $discount = $promo_cart->promotionrule->discount_value;
                                $promo_cart->disc_val_str = '-'.$promo_cart->promotionrule->discount_value + 0;
                                $promo_cart->disc_val = '-'.$promo_cart->promotionrule->discount_value + 0;
                            }

                            $cart_promo_wo_tax = $discount / (1 + $cart_vat);
                            $cart_promo_tax = $discount - $cart_promo_wo_tax;
                            $cart_promo_taxes = $cart_promo_taxes + $cart_promo_tax;
                            
                            foreach ($taxes as $tax) {
                                if (!empty($tax->total_tax)) {
                                    $tax_reduction = ($tax->total_tax_before_cart_promo / $vat_before_cart_promo) * $cart_promo_tax;
                                    $tax->total_tax = $tax->total_tax - $tax_reduction;
                                }
                            }

                            $discount_cart_promo = $discount_cart_promo + $discount;
                            $discount_cart_promo_wo_tax = $discount_cart_promo_wo_tax + $cart_promo_wo_tax;
                            $acquired_promo_carts[] = $promo_cart;

                        }
                    }
                    
                }

                $coupon_carts = Coupon::join('promotion_rules', function($q) use($subtotal)
                {
                    $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.discount_object_type', '=', 'cash_rebate')->where('promotion_rules.coupon_redeem_rule_value', '<=', $subtotal);
                })->excludeDeleted()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function($q) use ($retailer)
                {
                    $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                })
                ->whereHas('issuedcoupons',function($q) use($user)
                {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
                })->with(array('issuedcoupons' => function($q) use($user)
                {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
                }))
                ->get();

                $available_coupon_carts = array();
                $cart_discount_by_percentage_counter = 0;
                $discount_cart_coupon = 0;
                $discount_cart_coupon_wo_tax = 0;
                $total_cart_coupon_discount = 0;
                $cart_coupon_taxes = 0;
                $acquired_coupon_carts = array();
                if(!empty($used_cart_coupons)) {
                    foreach($used_cart_coupons as $used_cart_coupon) {
                        if(!empty($used_cart_coupon->issuedcoupon->coupon_redeem_rule_value)) {
                            if($subtotal >= $used_cart_coupon->issuedcoupon->coupon_redeem_rule_value) {
                                if($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                                    $used_cart_coupon->disc_val_str = '-'.($used_cart_coupon->issuedcoupon->discount_value * 100).'%';
                                    $used_cart_coupon->disc_val = '-'.($used_cart_coupon->issuedcoupon->discount_value * $subtotal);
                                    $discount = $subtotal * $used_cart_coupon->issuedcoupon->discount_value;
                                    $cart_discount_by_percentage_counter++;
                                } elseif($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
                                    $used_cart_coupon->disc_val_str = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                    $used_cart_coupon->disc_val = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                    $discount = $used_cart_coupon->issuedcoupon->discount_value;
                                }

                                $cart_coupon_wo_tax = $discount / (1 + $cart_vat);
                                $cart_coupon_tax = $discount - $cart_coupon_wo_tax;

                                foreach ($taxes as $tax) {
                                    if (!empty($tax->total_tax)) {
                                        $tax_reduction = ($tax->total_tax_before_cart_promo / $vat_before_cart_promo) * $cart_coupon_tax;
                                        $tax->total_tax = $tax->total_tax - $tax_reduction;
                                    }
                                }
                                
                                $cart_coupon_taxes = $cart_coupon_taxes + $cart_coupon_tax;
                                $discount_cart_coupon = $discount_cart_coupon + $discount;
                                $discount_cart_coupon_wo_tax = $discount_cart_coupon_wo_tax + $cart_coupon_wo_tax;

                                $total_cart_coupon_discount = $total_cart_coupon_discount + $discount;
                                $acquired_coupon_carts[] = $used_cart_coupon;
                            } else {
                                $this->beginTransaction();
                                $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $used_cart_coupon->issued_coupon_id)->first();
                                $issuedcoupon->makeActive();
                                $issuedcoupon->save();
                                $used_cart_coupon->delete(TRUE);
                                $this->commit();
                            }
                        }
                    }
                }

                if(!empty($coupon_carts)) {
                    foreach($coupon_carts as $coupon_cart) {
                        if($subtotal >= $coupon_cart->coupon_redeem_rule_value) {
                            if($coupon_cart->rule_type == 'cart_discount_by_percentage') {
                                if($cart_discount_by_percentage_counter == 0) { // prevent more than one cart_discount_by_percentage
                                    $discount = $subtotal * $coupon_cart->discount_value;
                                    $cartdiscounts = $cartdiscounts + $discount;
                                    $coupon_cart->disc_val_str = '-'.($coupon_cart->discount_value * 100).'%';
                                    $coupon_cart->disc_val = '-'.($subtotal * $coupon_cart->discount_value);
                                    $available_coupon_carts[] = $coupon_cart;
                                    $cart_discount_by_percentage_counter++;
                                }
                            } elseif ($coupon_cart->rule_type == 'cart_discount_by_value') {
                                $discount = $coupon_cart->discount_value;
                                $cartdiscounts = $cartdiscounts + $discount;
                                $coupon_cart->disc_val_str = '-'.$coupon_cart->discount_value + 0;
                                $coupon_cart->disc_val = '-'.$coupon_cart->discount_value + 0;
                                $available_coupon_carts[] = $coupon_cart;
                            }
                        } else {
                            $coupon_cart->disc_val = $coupon_cart->rule_value;
                        }
                    }
                }

                $subtotal = $subtotal - $discount_cart_promo - $discount_cart_coupon;
                $subtotal_wo_tax = $subtotal_wo_tax - $discount_cart_promo_wo_tax - $discount_cart_coupon_wo_tax;
                $vat = $vat - $cart_promo_taxes - $cart_coupon_taxes;
                // dd($cart_coupon_taxes);

                $cartsummary = new stdclass();
                $cartsummary->vat = round($vat, 2);
                $cartsummary->total_to_pay = round($subtotal, 2);
                $cartsummary->subtotal_wo_tax = $subtotal_wo_tax; 
                $cartsummary->acquired_promo_carts = $acquired_promo_carts;
                $cartsummary->used_cart_coupons = $acquired_coupon_carts;
                $cartsummary->available_coupon_carts = $available_coupon_carts;
                $cartsummary->subtotal_before_cart_promo = round($subtotal_before_cart_promo, 2);
                $cartsummary->taxes = $taxes;
                $cartsummary->subtotal_before_cart_promo_without_tax = $subtotal_before_cart_promo_without_tax;
                $cartsummary->vat_before_cart_promo = $vat_before_cart_promo;
                $cartdata->cartsummary = $cartsummary;
                // $cartdata->attributes = $attributes;
            } else {
                foreach ($cartdata->cartdetails as $cartdetail) {
                    $attributes = array();
                    $product_vat_value = 0;
                    $original_price = $cartdetail->variant->price;
                    $subtotal_wo_tax = $subtotal_wo_tax + ($original_price * $cartdetail->quantity);
                    $original_ammount = $original_price * $cartdetail->quantity;

                    $available_product_coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                            inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                            WHERE 
                                ic.expired_date >= NOW() 
                                AND p.merchant_id = :merchantid 
                                AND prr.retailer_id = :retailerid 
                                AND ic.user_id = :userid 
                                AND prod.product_id = :productid 
                                
                            '), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $cartdetail->product_id));
 
                    $cartdetail->available_product_coupons = count($available_product_coupons);

                    if (!is_null($cartdetail->tax1)) {
                        $tax1 = $cartdetail->tax1->tax_value;
                        if (!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $pwt = $original_price + ($original_price * $tax2) ;
                                $tax1_value = $pwt * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                $tax1_value = $original_price * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            }
                        } else {
                            $tax1_value = $original_price * $tax1;
                            $tax1_total_value = $tax1_value * $cartdetail->quantity;
                        }
                        foreach ($taxes as $tax) {
                            if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax + $tax1_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax1_total_value;
                            }
                        }
                    } else {
                        $tax1 = 0;
                    }

                    if (!is_null($cartdetail->tax2)) {
                        $tax2 = $cartdetail->tax2->tax_value;    
                        $tax2_value = $original_price * $tax2;
                        $tax2_total_value = $tax2_value * $cartdetail->quantity;
                        foreach ($taxes as $tax) {
                            if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax + $tax2_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax2_total_value;
                            }
                        }
                    } else {
                        $tax2 = 0;
                    }

                    if(!is_null($cartdetail->tax2)) {
                        if($cartdetail->tax2->tax_type == 'service') {
                            $product_price_with_tax = $original_price * (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                        } elseif($cartdetail->tax2->tax_type == 'luxury') {
                            $product_price_with_tax = $original_price * (1 + $tax1 + $tax2);
                        }
                    } else {
                        $product_price_with_tax = $original_price * (1 + $tax1);
                    }

                    $product_vat = ($product_price_with_tax - $original_price) * $cartdetail->quantity;
                    $vat = $vat + $product_vat;
                    
                    $product_price_with_tax = $product_price_with_tax * $cartdetail->quantity;
                    $ammount_after_promo = $product_price_with_tax;
                    $subtotal = $subtotal + $product_price_with_tax;
                    $temp_price = $original_ammount;

                    $promo_for_this_product_array = array();
                    $promo_filters = array_filter($promo_products, function($v) use ($cartdetail) { return $v->product_id == $cartdetail->product_id; });
                    // dd($promo_filters);
                    foreach($promo_filters as $promo_filter) {
                        $promo_for_this_product = new stdclass();
                        if($promo_filter->rule_type == 'product_discount_by_percentage') {
                            $discount = $promo_filter->discount_value * $original_price;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value * 100;
                        } elseif($promo_filter->rule_type == 'product_discount_by_value') {
                            $discount = $promo_filter->discount_value;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value;
                        } elseif ($used_product_coupon->issuedcoupon->rule_type == 'new_product_price') {
                            $discount = $promo_filter->discount_value;
                            $promo_for_this_product->discount_str = $promo_filter->discount_value;
                        }
                        $promo_for_this_product->promotion_id = $promo_filter->promotion_id;
                        $promo_for_this_product->promotion_name = $promo_filter->promotion_name;
                        $promo_for_this_product->rule_type = $promo_filter->rule_type;
                        $promo_for_this_product->discount = $discount * $cartdetail->quantity;
                        $ammount_after_promo = $ammount_after_promo - $promo_for_this_product->discount;
                        $temp_price = $temp_price - $promo_for_this_product->discount;

                        $promo_wo_tax = $discount / (1 + $product_vat_value);
                        if(!is_null($cartdetail->tax1)) {
                            $tax1 = $cartdetail->tax1->tax_value;
                            if(!is_null($cartdetail->tax2)) {
                                $tax2 = $cartdetail->tax2->tax_value;
                                if ($cartdetail->tax2->tax_type == 'service') {
                                    $pwt = $discount;
                                    $tax1_value = $pwt * $tax1;
                                    $tax1_total_value = $tax1_value * $cartdetail->quantity;
                                } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                    $tax1_value = $discount * $tax1;
                                    $tax1_total_value = $tax1_value * $cartdetail->quantity;
                                }
                            } else {
                                $tax1_value = $discount * $tax1;
                                $tax1_total_value = $tax1_value * $cartdetail->quantity;
                            }
                            foreach($taxes as $tax) {
                                if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                }
                            }
                        }
                        
                        if(!is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;    
                            $tax2_value = $discount * $tax2;
                            $tax2_total_value = $tax2_value * $cartdetail->quantity;
                            
                            foreach ($taxes as $tax) {
                                if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                                }
                            }
                        }

                        if(!is_null($cartdetail->tax2)) {
                            if($cartdetail->tax2->tax_type == 'service') {
                                $promo_with_tax = $discount * (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                            } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                $promo_with_tax = $discount * (1 + $tax1 + $tax2);
                            }
                        } else {
                            $promo_with_tax = $discount * (1 + $tax1);
                        }



                        $promo_vat = ($promo_with_tax - $discount) * $cartdetail->quantity;
                        // $promo_vat = ($discount * $cartdetail->quantity);
                        
                        
                        $vat = $vat - $promo_vat;
                        $promo_with_tax = $promo_with_tax * $cartdetail->quantity;
                        $subtotal = $subtotal - $promo_with_tax;
                        $subtotal_wo_tax = $subtotal_wo_tax - ($discount * $cartdetail->quantity);
                        $promo_for_this_product_array[] = $promo_for_this_product;
                    }
                    
                    $cartdetail->promo_for_this_product = $promo_for_this_product_array;

                    $coupon_filter = array();
                    foreach($used_product_coupons as $used_product_coupon) {
                        // dd($used_product_coupon->cartdetail);
                        if($used_product_coupon->cartdetail->product_variant_id == $cartdetail->product_variant_id) {
                            if($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_percentage') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value * $original_price;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value * 100;
                            } elseif($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_value') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                            } elseif ($used_product_coupon->issuedcoupon->rule_type == 'new_product_price') {
                                $discount = $used_product_coupon->issuedcoupon->discount_value + 0;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value + 0;
                            }
                            $temp_price = $temp_price - $discount;
                            $used_product_coupon->discount = $discount;
                            $ammount_after_promo = $ammount_after_promo - $discount;
                            // $coupon_wo_tax = $discount / (1 + $product_vat_value);

                            if(!is_null($cartdetail->tax1)) {
                                $tax1 = $cartdetail->tax1->tax_value;
                                if(!is_null($cartdetail->tax2)) {
                                    $tax2 = $cartdetail->tax2->tax_value;
                                    if ($cartdetail->tax2->tax_type == 'service') {
                                        $pwt = $discount + ($discount * $tax2) ;
                                        $tax1_value = $pwt * $tax1;
                                        $tax1_total_value = $tax1_value;
                                    } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                        $tax1_value = $discount * $tax1;
                                        $tax1_total_value = $tax1_value;
                                    }
                                } else {
                                    $tax1_value = $discount * $tax1;
                                    $tax1_total_value = $tax1_value;
                                }
                                foreach($taxes as $tax) {
                                    if($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                        $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                        $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                    }
                                }
                            }
                            
                            if(!is_null($cartdetail->tax2)) {
                                $tax2 = $cartdetail->tax2->tax_value;    
                                $tax2_value = $discount * $tax2;
                                $tax2_total_value = $tax2_value;
                                
                                foreach ($taxes as $tax) {
                                    if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                        $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                        $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                                    }
                                }
                            }

                            if(!is_null($cartdetail->tax2)) {
                                if($cartdetail->tax2->tax_type == 'service') {
                                    $coupon_with_tax = $discount * (1 + $tax1 + $tax2 + ($tax1 * $tax2));        
                                } elseif($cartdetail->tax2->tax_type == 'luxury') {
                                    $coupon_with_tax = $discount * (1 + $tax1 + $tax2);
                                }
                            } else {
                                $coupon_with_tax = $discount * (1 + $tax1);
                            }
                            // $coupon_vat = ($discount - $coupon_wo_tax);
                            // $vat = $vat - $coupon_vat;
                            // $subtotal = $subtotal - $discount;
                            // $subtotal_wo_tax = $subtotal_wo_tax - $coupon_wo_tax;
                            $coupon_vat = ($coupon_with_tax - $discount);
                            $vat = $vat - $coupon_vat;
                            $subtotal = $subtotal - $coupon_with_tax;
                            $subtotal_wo_tax = $subtotal_wo_tax - $discount;
                            $coupon_filter[] = $used_product_coupon;
                        }
                    }
                    // dd($coupon_filter[1]);
                    $cartdetail->coupon_for_this_product = $coupon_filter;

                    $cartdetail->original_price = $original_price;
                    $cartdetail->original_ammount = $original_ammount;
                    $cartdetail->ammount_after_promo = $ammount_after_promo;

                    if($cartdetail->attributeValue1['value']) {
                        $attributes[] = $cartdetail->attributeValue1['value'];
                    }
                    if($cartdetail->attributeValue2['value']) {
                        $attributes[] = $cartdetail->attributeValue2['value'];
                    }
                    if($cartdetail->attributeValue3['value']) {
                        $attributes[] = $cartdetail->attributeValue3['value'];
                    }
                    if($cartdetail->attributeValue4['value']) {
                        $attributes[] = $cartdetail->attributeValue4['value'];
                    }
                    if($cartdetail->attributeValue5['value']) {
                        $attributes[] = $cartdetail->attributeValue5['value'];
                    }
                    $cartdetail->attributes = $attributes;
                }

                if (count($cartdata->cartdetails) > 0 && $subtotal_wo_tax > 0) {
                    $cart_vat = $vat / $subtotal_wo_tax;
                } else {
                    $cart_vat = 0;
                }


                $subtotal_before_cart_promo_without_tax = $subtotal_wo_tax;
                $vat_before_cart_promo = $vat;
                $cartdiscounts = 0;
                $acquired_promo_carts = array();
                $discount_cart_promo = 0;
                $discount_cart_promo_with_tax = 0;
                $discount_cart_coupon = 0;
                $cart_promo_taxes = 0;
                $subtotal_before_cart_promo = $subtotal;

                if (!empty($promo_carts)) {
                    foreach ($promo_carts as $promo_cart) {
                        if ($subtotal_before_cart_promo_without_tax >= $promo_cart->promotionrule->rule_value) {
                            if ($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') {
                                $discount = $subtotal_before_cart_promo_without_tax * $promo_cart->promotionrule->discount_value;
                                $promo_cart->disc_val_str = '-'.($promo_cart->promotionrule->discount_value * 100).'%';
                                $promo_cart->disc_val = '-'.($subtotal_before_cart_promo_without_tax * $promo_cart->promotionrule->discount_value);
                            } elseif ($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') {
                                $discount = $promo_cart->promotionrule->discount_value;
                                $promo_cart->disc_val_str = '-'.$promo_cart->promotionrule->discount_value + 0;
                                $promo_cart->disc_val = '-'.$promo_cart->promotionrule->discount_value + 0;
                            }

                            $cart_promo_with_tax = $discount * (1 + $cart_vat);
                            
                            // $cart_promo_tax = $cart_promo_with_tax - $discount;
                            
                            $cart_promo_tax = $discount / $subtotal_wo_tax * $vat_before_cart_promo;
                            $cart_promo_taxes = $cart_promo_taxes + $cart_promo_tax;
                            
                            foreach ($taxes as $tax) {
                                if (!empty($tax->total_tax)) {
                                    // $tax_reduction = ($tax->total_tax_before_cart_promo / $vat_before_cart_promo) * $cart_promo_tax;
                                    $tax_reduction = ($discount / $subtotal_wo_tax) * $cart_promo_tax;
                                    $tax->total_tax = $tax->total_tax - $tax_reduction;
                                }
                            }

                            $discount_cart_promo = $discount_cart_promo + $discount;
                            $discount_cart_promo_with_tax = $discount_cart_promo_with_tax - $cart_promo_with_tax;
                            $acquired_promo_carts[] = $promo_cart;
                            // dd($cart_promo_with_tax);
                        }
                    }
                    
                }

                $coupon_carts = Coupon::join('promotion_rules', function($q) use($subtotal_before_cart_promo_without_tax)
                {
                    $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.discount_object_type', '=', 'cash_rebate')->where('promotion_rules.coupon_redeem_rule_value', '<=', $subtotal_before_cart_promo_without_tax);
                })->excludeDeleted()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function($q) use ($retailer)
                {
                    $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                })
                ->whereHas('issuedcoupons',function($q) use($user)
                {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
                })->with(array('issuedcoupons' => function($q) use($user)
                {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->excludeDeleted();
                }))
                ->get();

                $available_coupon_carts = array();
                $cart_discount_by_percentage_counter = 0;
                $discount_cart_coupon = 0;
                $discount_cart_coupon_with_tax = 0;
                $total_cart_coupon_discount = 0;
                $cart_coupon_taxes = 0;
                $acquired_coupon_carts = array();
                if(!empty($used_cart_coupons)) {
                    foreach($used_cart_coupons as $used_cart_coupon) {
                        if(!empty($used_cart_coupon->issuedcoupon->coupon_redeem_rule_value)) {
                            if($subtotal_before_cart_promo_without_tax >= $used_cart_coupon->issuedcoupon->coupon_redeem_rule_value) {
                                if($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                                    $used_cart_coupon->disc_val_str = '-'.($used_cart_coupon->issuedcoupon->discount_value * 100).'%';
                                    $used_cart_coupon->disc_val = '-'.($used_cart_coupon->issuedcoupon->discount_value * $subtotal_before_cart_promo_without_tax);
                                    $discount = $subtotal_before_cart_promo_without_tax * $used_cart_coupon->issuedcoupon->discount_value;
                                    $cart_discount_by_percentage_counter++;
                                } elseif($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
                                    $used_cart_coupon->disc_val_str = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                    $used_cart_coupon->disc_val = '-'.$used_cart_coupon->issuedcoupon->discount_value + 0;
                                    $discount = $used_cart_coupon->issuedcoupon->discount_value;
                                }

                                $cart_coupon_with_tax = $discount * (1 + $cart_vat);
                                // $cart_coupon_tax = $cart_coupon_with_tax - $discount;
                                $cart_coupon_tax = $discount / $subtotal_wo_tax * $vat_before_cart_promo;
                                $cart_coupon_taxes = $cart_coupon_taxes + $cart_coupon_tax;

                                foreach ($taxes as $tax) {
                                    if (!empty($tax->total_tax)) {
                                        $tax_reduction = ($tax->total_tax_before_cart_promo / $vat_before_cart_promo) * $cart_coupon_tax;
                                        $tax->total_tax = $tax->total_tax - $tax_reduction;
                                    }
                                }
                                
                                $discount_cart_coupon = $discount_cart_coupon + $discount;
                                $discount_cart_coupon_with_tax = $discount_cart_coupon_with_tax - $cart_coupon_with_tax;

                                $total_cart_coupon_discount = $total_cart_coupon_discount + $discount;
                                $acquired_coupon_carts[] = $used_cart_coupon;
                            } else {
                                $this->beginTransaction();
                                $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $used_cart_coupon->issued_coupon_id)->first();
                                $issuedcoupon->makeActive();
                                $issuedcoupon->save();
                                $used_cart_coupon->delete(TRUE);
                                $this->commit();
                            }
                        }
                    }
                }
                
                if (!empty($coupon_carts)) {
                    foreach ($coupon_carts as $coupon_cart) {
                        if ($subtotal_before_cart_promo_without_tax >= $coupon_cart->coupon_redeem_rule_value) {
                            if ($coupon_cart->rule_type == 'cart_discount_by_percentage') {
                                if ($cart_discount_by_percentage_counter == 0) { // prevent more than one cart_discount_by_percentage
                                    $discount = $subtotal_before_cart_promo_without_tax * $coupon_cart->discount_value;
                                    $cartdiscounts = $cartdiscounts + $discount;
                                    $coupon_cart->disc_val_str = '-'.($coupon_cart->discount_value * 100).'%';
                                    $coupon_cart->disc_val = '-'.($subtotal_before_cart_promo_without_tax * $coupon_cart->discount_value);
                                    $available_coupon_carts[] = $coupon_cart;
                                    $cart_discount_by_percentage_counter++;
                                }
                            } elseif ($coupon_cart->rule_type == 'cart_discount_by_value') {
                                $discount = $coupon_cart->discount_value;
                                $cartdiscounts = $cartdiscounts + $discount;
                                $coupon_cart->disc_val_str = '-'.$coupon_cart->discount_value + 0;
                                $coupon_cart->disc_val = '-'.$coupon_cart->discount_value + 0;
                                $available_coupon_carts[] = $coupon_cart;
                            }
                        } else {
                            $coupon_cart->disc_val = $coupon_cart->rule_value;
                        }
                    }
                }
                // dd($discount_cart_coupon);
                $subtotal_wo_tax = $subtotal_wo_tax - $discount_cart_promo - $discount_cart_coupon;
                $subtotal = $subtotal + $discount_cart_promo_with_tax + $discount_cart_coupon_with_tax;
                $vat = $vat - $cart_promo_taxes - $cart_coupon_taxes;
                // dd($cart_coupon_taxes);
                
                $cartsummary = new stdclass();
                $cartsummary->vat = round($vat, 2);
                $cartsummary->total_to_pay = round($subtotal, 2);
                $cartsummary->subtotal_wo_tax = $subtotal_wo_tax; 
                $cartsummary->acquired_promo_carts = $acquired_promo_carts;
                $cartsummary->used_cart_coupons = $acquired_coupon_carts;
                $cartsummary->available_coupon_carts = $available_coupon_carts;
                $cartsummary->subtotal_before_cart_promo = round($subtotal_before_cart_promo, 2);
                $cartsummary->taxes = $taxes;
                $cartsummary->subtotal_before_cart_promo_without_tax = $subtotal_before_cart_promo_without_tax;
                $cartsummary->vat_before_cart_promo = $vat_before_cart_promo;
                $cartdata->cartsummary = $cartsummary;
            }

            return View::make('mobile-ci.thankyou', array('retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata));
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
            $user_email = $user->user_email;
            return View::make('mobile-ci.welcome', array('retailer'=>$retailer, 'user'=>$user, 'cartdata' => $cartdata, 'user_email' => $user_email));
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
        $user = null;
        $product_id = null;
        $activityCart = Activity::mobileci()
                            ->setActivityType('cart');
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
            if (empty($cart)) {
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
            if (empty($cartdetail)) {
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
            
            $merchant_id = $retailer->parent_id;
            $prefix = DB::getTablePrefix();
            $retailer_id = $retailer->merchant_id;
            $promo_products = Promotion::with('promotionrule')->select('promotions.*')
                ->join('promotion_rules', function($join) use ($merchant_id, $prefix) {
                    $join->on('promotion_rules.promotion_id', '=', 'promotions.promotion_id');
                    $join->on('promotions.promotion_type', '=', DB::raw("'product'"));
                    $join->on('promotions.status', '=', DB::raw("'active'"));
                    $join->on('promotions.is_coupon', '=', DB::raw("'N'"));
                    $join->on('promotions.merchant_id', '=', DB::raw($merchant_id));
                    $join->on(DB::raw("(({$prefix}promotions.begin_date <= NOW() AND {$prefix}promotions.end_date >= NOW())"), 'OR', 
                                      DB::raw("({$prefix}promotions.begin_date <= NOW() AND {$prefix}promotions.is_permanent = 'Y'))"));
                })
                ->join('promotion_retailer', function($join) use ($retailer_id) {
                    $join->on('promotion_retailer.promotion_id', '=', 'promotions.promotion_id');
                    $join->on('promotion_retailer.retailer_id', '=', DB::raw($retailer_id));
                })
                ->join('products', DB::raw("(({$prefix}promotion_rules.discount_object_type=\"product\" AND {$prefix}promotion_rules.discount_object_id1={$prefix}products.product_id)"),
                       'OR',
                       DB::raw("                    (
                            ({$prefix}promotion_rules.discount_object_type=\"family\") AND 
                            (({$prefix}promotion_rules.discount_object_id1 IS NULL) OR ({$prefix}promotion_rules.discount_object_id1={$prefix}products.category_id1)) AND 
                            (({$prefix}promotion_rules.discount_object_id2 IS NULL) OR ({$prefix}promotion_rules.discount_object_id2={$prefix}products.category_id2)) AND
                            (({$prefix}promotion_rules.discount_object_id3 IS NULL) OR ({$prefix}promotion_rules.discount_object_id3={$prefix}products.category_id3)) AND
                            (({$prefix}promotion_rules.discount_object_id4 IS NULL) OR ({$prefix}promotion_rules.discount_object_id4={$prefix}products.category_id4)) AND
                            (({$prefix}promotion_rules.discount_object_id5 IS NULL) OR ({$prefix}promotion_rules.discount_object_id5={$prefix}products.category_id5))
                        ))"))->where('products.product_id', $product_id)->get();
    
            $variant_price = $product->variants->find($product_variant_id)->price;
            $price_after_promo = $variant_price;

            foreach ($promo_products as $promo) {
                if($promo->promotionrule->rule_type == 'product_discount_by_percentage') {
                    $discount = $promo->promotionrule->discount_value * $variant_price;
                } elseif($promo->promotionrule->rule_type == 'product_discount_by_value') {
                    $discount = $promo->promotionrule->discount_value;
                }

                $price_after_promo = $price_after_promo - $discount;
            }

            $activityCoupon = array();

            foreach ($coupons as $coupon) {
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

                // if ($used_coupons->coupon->couponrule->rule_type == 'product_discount_by_percentage') {
                //     $discount = $used_coupons->coupon->couponrule->discount_value * $variant_price;
                // } elseif ($used_coupons->coupon->couponrule->rule_type == 'product_discount_by_value') {
                //     $discount = $used_coupons->coupon->couponrule->discount_value;
                // }
                // if($discount <= $price_after_promo) {
                    // $price_after_promo = $price_after_promo - $discount;
                    $cartcoupon = new CartCoupon;
                    $cartcoupon->issued_coupon_id = $coupon;
                    $cartcoupon->object_type = 'cart_detail';
                    $cartcoupon->object_id = $cartdetail->cart_detail_id;
                    $cartcoupon->save();
                    $used_coupons->status = 'deleted';
                    $used_coupons->save();
                    $activityCoupon[] = $used_coupons;
                // }
                // dd($used_coupons->coupon->couponrule->discount_value);
            }

            $coupons = DB::select(DB::raw('SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y"
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
                WHERE ic.expired_date >= "'.Carbon::now().'" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND prod.product_id = :productid AND ic.expired_date >= "'. Carbon::now() .'"'), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $product->product_id));
            
            $cartdetail->available_coupons = $coupons;
            
            $this->response->message = 'success';
            $this->response->data = $cartdetail;

            $activityCartNotes = sprintf('Add to cart: %s', $product->product_id);
            $activityCart->setUser($user)
                            ->setActivityName('add_to_cart')
                            ->setActivityNameLong('Add To Cart ' . $product->product_name)
                            ->setObject($product)
                            ->setNotes($activityCartNotes)
                            ->responseOK()
                            ->save();
            foreach ($promo_products as $promo) {
                $activityChild = Activity::parent($activityCart)
                                    ->setObject($promo)
                                    ->setUser($user)
                                    ->setNotes($promo->promotion_name)
                                    ->responseOK()
                                    ->save();
            }

            foreach ($activityCoupon as $_coupon) {
                $activityChild = Activity::parent($activityCart)
                                    ->setObject($_coupon)
                                    ->setUser($user)
                                    ->setNotes($_coupon->coupon->promotion_name)
                                    ->responseOK()
                                    ->save();
            }

            $this->commit();

        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            $activityCartNotes = sprintf('Add to cart: %s', $product_id);
            $activityCart->setUser($user)
                            ->setActivityName('add_to_cart')
                            ->setActivityNameLong('Add To Cart Failed')
                            ->setObject(null)
                            ->setNotes($activityCartNotes)
                            ->responseFailed()
                            ->save();

            return $e;
        }
        
        return $this->render();
    }

    public function postAddProductCouponToCart()
    {
        $user = null;
        $product_id = null;
        $activityCart = Activity::mobileci()
                            ->setActivityType('cart');
        try {
            $this->registerCustomValidation();

            $retailer = $this->getRetailerInfo();

            $user = $this->getLoggedInUser();

            $product_id = OrbitInput::post('productid');
            $product_variant_id = OrbitInput::post('productvariantid');
            $coupons = (array) OrbitInput::post('coupons');

            $validator = \Validator::make(
                array(
                    'product_id' => $product_id,
                    'product_variant_id' => $product_variant_id,
                ),
                array(
                    'product_id' => 'required|orbit.exists.product',
                    'product_variant_id' => 'required|orbit.exists.productvariant',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            
            $this->beginTransaction();

            $cart = Cart::where('status', 'active')->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->first();
            if (empty($cart)) {
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

            $cartdetail = CartDetail::excludeDeleted()->where('product_id', $product_id)->where('product_variant_id', $product_variant_id)->where('cart_id', $cart->cart_id)->first();
            
            if(!empty($cartdetail)){
            
                $activityCoupon = array();

                foreach ($coupons as $coupon) {
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
                    $activityCoupon[] = $used_coupons;
                }
                
                $this->response->message = 'success';
                $this->response->data = $cartdetail;
                
                foreach ($activityCoupon as $_coupon) {
                    $activityCartNotes = sprintf('Add coupon to cart: %s', $product->product_id);
                    $activityCart->setUser($user)
                                    ->setActivityName('add_coupons_to_cart')
                                    ->setActivityNameLong('Add Coupons To Cart ' . $product->product_name)
                                    ->setObject($_coupon)
                                    ->setNotes($_coupon->coupon->promotion_name)
                                    ->responseOK()
                                    ->save();
                }

                $this->commit();
            } else {
                $this->response->message = 'failed';
            }

        } catch (Exception $e) {
            $this->rollback();
            // return $this->redirectIfNotLoggedIn($e);
            $activityCartNotes = sprintf('Add coupon to cart: %s', $product_id);
            $activityCart->setUser($user)
                            ->setActivityName('add_coupon_to_cart')
                            ->setActivityNameLong('Add Coupon To Cart Failed')
                            ->setObject(null)
                            ->setNotes($activityCartNotes)
                            ->responseFailed()
                            ->save();

            return $e;
        }
        
        return $this->render();
    }

    public function postAddCouponCartToCart()
    {
        $user = null;
        $couponid = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
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
            if (empty($cart)) {
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

            $activityPageNotes = sprintf('Added issued cart coupon id: %s', $couponid);
            $activityPage->setUser($user)
                            ->setActivityName('add_cart_coupon_to_cart')
                            ->setActivityNameLong('Add Cart Coupon To Cart')
                            ->setObject($used_coupons)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            $this->commit();

        } catch (Exception $e) {
            $this->rollback();
            $activityPageNotes = sprintf('Failed to add issued cart coupon id: %s', $couponid);
            $activityPage->setUser($user)
                            ->setActivityName('add_cart_coupon_to_cart')
                            ->setActivityNameLong('Failed To Add Cart Coupon To Cart')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
        
        return $this->render();
    }
    
    public function postDeleteFromCart()
    {
        $user = null;
        $cartdetailid = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
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

            $activityPageNotes = sprintf('Deleted cart item id: %s', $cartdetailid);
            $activityPage->setUser($user)
                            ->setActivityName('delete_item_from_cart')
                            ->setActivityNameLong('Delete Item From Cart')
                            ->setObject($cartdetail)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            $this->commit();
            return $this->render();

        } catch (Exception $e) {
            $this->rollback();
            $activityPageNotes = sprintf('Failed to delete cart item id: %s', $cartdetailid);
            $activityPage->setUser($user)
                            ->setActivityName('delete_item_from_cart')
                            ->setActivityNameLong('Failed To Delete Item From Cart')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postDeleteCouponFromCart()
    {
        $user = null;
        $couponid = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
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

            $activityPageNotes = sprintf('Deleted issued cart coupon id: %s', $couponid);
            $activityPage->setUser($user)
                            ->setActivityName('delete_cart_coupon_from_cart')
                            ->setActivityNameLong('Delete Cart Coupon From Cart')
                            ->setObject($issuedcoupon)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            $this->commit();
            return $this->render();

        } catch (Exception $e) {
            $this->rollback();
            $activityPageNotes = sprintf('Failed to delete issued cart coupon id: %s', $couponid);
            $activityPage->setUser($user)
                            ->setActivityName('delete_cart_coupon_from_cart')
                            ->setActivityNameLong('Failed To Delete Cart Coupon From Cart')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postUpdateCart()
    {
        $user = null;
        $quantity = 0;
        $cartdetailid = 0;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
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

            $activityPageNotes = sprintf('Updated cart item id: ' . $cartdetailid . ' quantity to %s', $quantity);
            $activityPage->setUser($user)
                            ->setActivityName('update_cart_item')
                            ->setActivityNameLong('Update Cart Item')
                            ->setObject($cartdetail)
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            $this->commit();
            return $this->render();

        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            $this->rollback();
            $activityPageNotes = sprintf('Failed to update cart item id: ' . $cartdetailid . ' quantity to %s', $quantity);
            $activityPage->setUser($user)
                            ->setActivityName('update_cart_item')
                            ->setActivityNameLong('Failed To Update Cart Item')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
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
            return \Redirect::to('/customer/logout');
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
        if (is_null($cart)) {
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
            if (is_null($cart)) {
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
