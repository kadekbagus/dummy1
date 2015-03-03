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
use \Transaction;
use \TransactionDetail;
use \TransactionDetailPromotion;
use \TransactionDetailCoupon;
use \TransactionDetailTax;
use Swift_Attachment;

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
            $retailer = $this->getRetailerInfo();

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
            
            $user_detail = UserDetail::where('user_id', $user->user_id)->first();
            $user_detail->last_visit_shop_id = $retailer->merchant_id;
            $user_detail->last_visit_any_shop = Carbon::now();
            $user_detail->save();

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

            $new_products = Product::with('media')
                ->whereHas('retailers', function($q) use($retailer)
                {
                    $q->where('product_retailer.retailer_id', $retailer->merchant_id);
                })
                ->active()
                ->where('new_from','<=', Carbon::now())
                ->where('new_until', '>=', Carbon::now())
                ->get();
            
            $promotion = Promotion::active()->where('is_coupon', 'N')->where('merchant_id', $retailer->parent_id)->whereHas('retailers', function($q) use ($retailer)
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y" and p.status = "active"
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

            $events = EventModel::active()->whereHas('retailers', function($q) use($retailer)
                {
                    $q->where('event_retailer.retailer_id', $retailer->merchant_id);
                })
                ->where('merchant_id', $retailer->parent->merchant_id)
                ->where('begin_date', '<=', Carbon::now())
                ->where('end_date', '>=', Carbon::now());

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
                        $event_families[] = Category::where('category_id', $events->link_object_id1)->active()->first();
                    }
                    if(!empty($events->link_object_id2)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id2)->active()->first();
                    }
                    if(!empty($events->link_object_id3)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id3)->active()->first();
                    }
                    if(!empty($events->link_object_id4)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id4)->active()->first();
                    }
                    if(!empty($events->link_object_id5)) {
                        $event_families[] = Category::where('category_id', $events->link_object_id5)->active()->first();
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

            $widgets = Widget::with('media')
                ->active()
                ->where('merchant_id', $retailer->parent->merchant_id)
                ->whereHas('retailers', function($q) use($retailer) 
                {
                    $q->where('retailer_id', $retailer->merchant_id);
                })
                ->orderBy('widget_order', 'ASC')
                ->take(4)
                ->get();

            $activityPageNotes = sprintf('Page viewed: %s', 'Home');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_home')
                            ->setActivityNameLong('View (Home Page)')
                            ->setObject(null)
                            ->setNotes($activityPageNotes)
                            ->setModuleName('Widget')
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.home', array('page_title'=>Lang::get('mobileci.page_title.home'), 'retailer' => $retailer, 'new_products' => $new_products, 'promo_products' => $promo_products, 'promotion' => $promotion, 'cartitems' => $cartitems, 'coupons' => $coupons, 'events' => $events, 'widgets' => $widgets, 'event_families' => $event_families, 'event_family_url_param' => $event_family_url_param))->withCookie($event_store);
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Home');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_home')
                            ->setActivityNameLong('View (Home Page) Failed')
                            ->setObject(null)
                            ->setModuleName('Widget')
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
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
        $activityfamilyid = null;
        $cat_name = null;
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();
            $families = Category::has('product1')->where('merchant_id', $retailer->parent_id)->active()->get();
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
                    $activityfamilyid = $family1;
                }
                if(!empty($family2)) {
                    $lvl2 = $this->getProductListCatalogue($array_of_families_lvl2, 2, $family2, '');
                    $activityfamilyid = $family2;
                }
                if(!empty($family3)) {
                    $lvl3 = $this->getProductListCatalogue($array_of_families_lvl3, 3, $family3, '');
                    $activityfamilyid = $family3;
                }
                if(!empty($family4)) {
                    $lvl4 = $this->getProductListCatalogue($array_of_families_lvl4, 4, $family4, '');
                    $activityfamilyid = $family4;
                }
                if(!empty($family5)) {
                    $lvl5 = $this->getProductListCatalogue($array_of_families_lvl5, 5, $family5, '');
                    $activityfamilyid = $family5;
                }
            }

            if (is_null($activityfamilyid)) {
                $activityfamily = null;
                $cat_name = null;
            } else {
                $activityfamily = Category::where('category_id', $activityfamilyid)->first();
                $cat_name = $activityfamily->category_name;
            }
            $activityPageNotes = sprintf('Page viewed: %s', 'Catalogue');
            $activityPage->setUser($user)
                            ->setActivityName('view_catalogue')
                            ->setActivityNameLong('View Cataloguge ' . $cat_name)
                            ->setObject($activityfamily)
                            ->setModuleName('Catalogue')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.catalogue', array('page_title'=>Lang::get('mobileci.page_title.catalogue'), 'retailer' => $retailer, 'families' => $families, 'cartitems' => $cartitems, 'hasFamily' => $hasFamily, 'lvl1' => $lvl1, 'lvl2' => $lvl2, 'lvl3' => $lvl3, 'lvl4' => $lvl4, 'lvl5' => $lvl5));
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Catalogue');
            $activityPage->setUser($user)
                            ->setActivityName('view_catalogue')
                            ->setActivityNameLong('View Cataloguge Failed')
                            ->setObject(null)
                            ->setModuleName('Catalogue')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            // return $this->redirectIfNotLoggedIn($e);
            return $e;
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
                        })->where('merchant_id', $retailer->parent_id)->active();

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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y" and p.status = "active"
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
                $temp_price = $min_price;
                $promo_for_this_product = array_filter($promotions, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($promo_for_this_product) > 0) {
                    $discounts=0;
                    foreach($promo_for_this_product as $promotion) {
                        if($promotion->rule_type == 'product_discount_by_percentage') {
                            $discount = min($prices) * $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'product_discount_by_value') {
                            $discount = $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'new_product_price') {
                            $new_price = $min_price - $promotion->discount_value;
                            $discount = $new_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        }
                        $temp_price = $temp_price - $discount;
                    }
                    $product->on_promo = true;
                    $product->priceafterpromo = $min_price - $discounts;
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
                $pagetitle = Lang::get('mobileci.page_title.new_products');
                $activityPageNotes = sprintf('Page viewed: New Product Page, keyword: %s', $keyword);
                $activityPage->setUser($user)
                                ->setActivityName('view_new_product')
                                ->setActivityNameLong('View (New Product Page)')
                                ->setObject(null)
                                ->setModuleName('New Product')
                                ->setNotes($activityPageNotes)
                                ->responseOK()
                                ->save();
            } else {
                $activityPageNotes = sprintf('Page viewed: Search Page, keyword: %s', $keyword);
                $activityPage->setUser($user)
                                ->setActivityName('view_search')
                                ->setActivityNameLong('View (Search Page)')
                                ->setObject(null)
                                ->setModuleName('Product')
                                ->setNotes($activityPageNotes)
                                ->responseOK()
                                ->save();
            }
            // if(!empty(OrbitInput::get('promo'))) {
            //     $pagetitle = Lang::get('mobileci.page_title.promotions');
            // }
            // if(!empty(OrbitInput::get('coupon'))) {
            //     $pagetitle = Lang::get('mobileci.page_title.coupons');
            // }

            

            return View::make('mobile-ci.search', array('page_title'=>$pagetitle, 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo));
            
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view: Search Page, keyword: %s', $keyword);
            $activityPage->setUser($user)
                            ->setActivityName('view_page_search')
                            ->setActivityNameLong('View (Search Page)')
                            ->setObject(null)
                            ->setModuleName('Product')
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
                        })->where('merchant_id', $retailer->parent_id)->active();

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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y" and p.status = "active"
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
                $temp_price = $min_price;
                $promo_for_this_product = array_filter($promotions, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($promo_for_this_product) > 0) {
                    $discounts=0;
                    foreach($promo_for_this_product as $promotion) {
                        if($promotion->rule_type == 'product_discount_by_percentage') {
                            $discount = min($prices) * $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'product_discount_by_value') {
                            $discount = $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'new_product_price') {
                            $new_price = $min_price - $promotion->discount_value;
                            $discount = $new_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        }
                        $temp_price = $temp_price - $discount;
                    }
                    $product->on_promo = true;
                    $product->priceafterpromo = $min_price - $discounts;
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
                            ->setModuleName('Catalogue')
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
                            ->setModuleName('Catalogue')
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
                        })->where('merchant_id', $retailer->parent_id)->active();

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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y" and p.status = "active"
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
                $temp_price = $min_price;
                $promo_for_this_product = array_filter($all_promotions, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($promo_for_this_product) > 0) {
                    $discounts=0;
                    foreach($promo_for_this_product as $promotion) {
                        if($promotion->rule_type == 'product_discount_by_percentage') {
                            $discount = min($prices) * $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'product_discount_by_value') {
                            $discount = $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'new_product_price') {
                            $new_price = $min_price - $promotion->discount_value;
                            $discount = $new_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        }
                        $temp_price = $temp_price - $discount;
                    }
                    $product->on_promo = true;
                    $product->priceafterpromo = $min_price - $discounts;
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
                            ->setModuleName('Catalogue')
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
                            ->setModuleName('Catalogue')
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
                        })->where('merchant_id', $retailer->parent_id)->active();

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
                $temp_price = $min_price;
                $promo_for_this_product = array_filter($all_promotions, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($promo_for_this_product) > 0) {
                    $discounts=0;
                    foreach($promo_for_this_product as $promotion) {
                        if($promotion->rule_type == 'product_discount_by_percentage') {
                            $discount = min($prices) * $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'product_discount_by_value') {
                            $discount = $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'new_product_price') {
                            $new_price = $min_price - $promotion->discount_value;
                            $discount = $new_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        }
                        $temp_price = $temp_price - $discount;
                    }
                    $product->on_promo = true;
                    $product->priceafterpromo = $min_price - $discounts;
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
                            ->setModuleName('Catalogue')
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
                            ->setModuleName('Catalogue')
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

            $promotions = Promotion::with('promotionrule')->active()->where('is_coupon', 'N')->where('merchant_id', $retailer->parent_id)->whereHas('retailers', function($q) use ($retailer)
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
                            ->setModuleName('Catalogue')
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
                            ->setModuleName('Catalogue')
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
                inner join ' . DB::getTablePrefix() . 'issued_coupons ic on p.promotion_id = ic.promotion_id AND ic.status = "active"
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
                            ->setModuleName('Catalogue')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.coupon-list', array('page_title' => Lang::get('mobileci.page_title.coupons'), 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems));
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Coupon List');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_coupon_list')
                            ->setActivityNameLong('View (Coupon List) Failed')
                            ->setObject(null)
                            ->setModuleName('Catalogue')
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

            $subfamilies = Category::active();
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
            })->where('merchant_id', $retailer->parent_id)->active()->where(function($q) use ($family_level, $family_id, $families) {
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y" and p.status = "active"
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
                $temp_price = $min_price;
                $promo_for_this_product = array_filter($promotions, function($v) use ($product) { return $v->product_id == $product->product_id; });
                if (count($promo_for_this_product) > 0) {
                    $discounts=0;
                    foreach($promo_for_this_product as $promotion) {
                        if($promotion->rule_type == 'product_discount_by_percentage') {
                            $discount = min($prices) * $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'product_discount_by_value') {
                            $discount = $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'new_product_price') {
                            $new_price = $min_price - $promotion->discount_value;
                            $discount = $new_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        }
                        $temp_price = $temp_price - $discount;
                    }
                    $product->on_promo = true;
                    $product->priceafterpromo = $min_price - $discounts;
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
                            ->setActivityName('view_catalogue')
                            ->setActivityNameLong('View Catalogue ' . $activityfamily->category_name)
                            ->setObject($activityfamily)
                            ->setModuleName('Catalogue')
                            ->setNotes($activityCategoryNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.product-list', array('retailer' => $retailer, 'data' => $data, 'subfamilies' => $subfamilies, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo, 'couponstocatchs' => $couponstocatchs));
            
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            // if($e->getMessage() === 'Invalid session data.') {
                $activityCategoryNotes = sprintf('Category viewed: %s', $family_id);
                $activityCategory->setUser($user)
                                ->setActivityName('view_catalogue')
                                ->setActivityNameLong('View Catalogue Failed')
                                ->setObject(null)
                                ->setModuleName('Catalogue')
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
            })->where('merchant_id', $retailer->parent_id)->active()->where(function($q) use ($family_level, $family_id, $families) {
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y" and p.status = "active"
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
                    $discounts=0;
                    $temp_price = $min_price;
                    foreach($promo_for_this_product as $promotion) {
                        if($promotion->rule_type == 'product_discount_by_percentage') {
                            $discount = min($prices) * $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'product_discount_by_value') {
                            $discount = $promotion->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        } elseif($promotion->rule_type == 'new_product_price') {
                            $new_price = $min_price - $promotion->discount_value;
                            $discount = $new_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $discounts = $discounts + $discount;
                        }
                        $temp_price = $temp_price - $discount;
                    }
                    $product->priceafterpromo = $min_price - $discounts;
                    $product->on_promo = true;
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
                        })->active()->where('product_id', $product_id)->first();
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y" and p.status = "active"
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
                $temp_price = $variant->price;

                if(!empty($promo_products)) {
                    $promo_price = $variant->price;
                    foreach($promo_products as $promo_filter) {
                        if($promo_filter->rule_type == 'product_discount_by_percentage') {
                            $discount = $promo_filter->discount_value * $variant->price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $promo_price = $promo_price - $discount;
                        } elseif($promo_filter->rule_type == 'product_discount_by_value') {
                            $discount = $promo_filter->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $promo_price = $promo_price - $discount;
                        } elseif($promo_filter->rule_type == 'new_product_price') {
                            $new_price = $promo_filter->discount_value;
                            $discount = $variant->price - $new_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $promo_price = $promo_price - $discount;
                        }
                        
                        $temp_price = $temp_price - $discount;
                    }
                }
                $variant->promo_price = $promo_price;
            }
            // set minimum price
            $min_price = min($prices);
            $product->min_price = $min_price + 0;

            $temp_price = $min_price;
            $min_promo_price = $product->min_price;
            if(!empty($promo_products)) {
                foreach($promo_products as $promo_filter) {
                    if($promo_filter->rule_type == 'product_discount_by_percentage') {
                        $discount = $promo_filter->discount_value * $product->min_price;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $min_promo_price = $min_promo_price - $discount;
                    } elseif($promo_filter->rule_type == 'product_discount_by_value') {
                        $discount = $promo_filter->discount_value;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $min_promo_price = $min_promo_price - $discount;
                    } elseif($promo_filter->rule_type == 'new_product_price') {
                        $new_price = $promo_filter->discount_value;
                        $discount = $min_price - $new_price;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $min_promo_price = $min_promo_price - $discount;
                    }
                    $temp_price = $temp_price - $discount;
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
                            ->setProduct($product)
                            ->setModuleName('Product')
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
                            ->setProduct($product)
                            ->setModuleName('Product')
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

            $product = Product::active()->where('product_id', $product_id)->first();

            $this->response->message = 'success';
            $this->response->data = $product;

            // $activityPageNotes = sprintf('Popup viewed: %s', 'Product');
            // $activityPage->setUser($user)
            //                 ->setActivityName('view_popup_product')
            //                 ->setActivityNameLong('View (Product Popup)')
            //                 ->setObject(null)
            //                 ->setNotes($activityPageNotes)
            //                 ->responseOK()
            //                 ->save();

            return $this->render();
        } catch (Exception $e) {
            // $activityPageNotes = sprintf('Failed to view Popup: %s', 'Product');
            // $activityPage->setUser($user)
            //                 ->setActivityName('view_popup_product')
            //                 ->setActivityNameLong('View (Product Popup) Failed')
            //                 ->setObject(null)
            //                 ->setNotes($activityPageNotes)
            //                 ->responseFailed()
            //                 ->save();

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

            $promotion = Promotion::active()->where('promotion_id', $promotion_id)->first();

            $this->response->message = 'success';
            $this->response->data = $promotion;

            // $activityPageNotes = sprintf('Popup viewed: %s', 'Cart Promotion');
            // $activityPage->setUser($user)
            //                 ->setActivityName('view_popup_cart_promo')
            //                 ->setActivityNameLong('View (Cart Promotion Popup)')
            //                 ->setObject(null)
            //                 ->setNotes($activityPageNotes)
            //                 ->responseOK()
            //                 ->save();

            return $this->render();
        } catch (Exception $e) {
            // $activityPageNotes = sprintf('Failed to view Popup: %s', 'Cart Promotion');
            // $activityPage->setUser($user)
            //                 ->setActivityName('view_popup_cart_promo')
            //                 ->setActivityNameLong('View (Cart Promotion Popup) Failed')
            //                 ->setObject(null)
            //                 ->setNotes($activityPageNotes)
            //                 ->responseFailed()
            //                 ->save();
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

            $promotion = Coupon::active()->where('promotion_id', $promotion_id)->first();

            $this->response->message = 'success';
            $this->response->data = $promotion;

            // $activityPageNotes = sprintf('Popup viewed: %s', 'Cart Coupon');
            // $activityPage->setUser($user)
            //                 ->setActivityName('view_popup_cart_coupon')
            //                 ->setActivityNameLong('View (Cart Coupon Popup)')
            //                 ->setObject(null)
            //                 ->setNotes($activityPageNotes)
            //                 ->responseOK()
            //                 ->save();

            return $this->render();
        } catch (Exception $e) {
            // $activityPageNotes = sprintf('Failed to view Popup: %s', 'Cart Coupon');
            // $activityPage->setUser($user)
            //                 ->setActivityName('view_popup_cart_coupon')
            //                 ->setActivityNameLong('View (Cart Coupon Popup) Failed')
            //                 ->setObject(null)
            //                 ->setNotes($activityPageNotes)
            //                 ->responseFailed()
            //                 ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postCloseCart()
    {
        try {
            $cartcode = OrbitInput::post('cartcode');
            // $cartdata = $this->getCartData();
            $cart = Cart::where('cart_code', $cartcode)->first();

            if($cart->status === 'cashier') {
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y" and p.status = "active"
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
            //         $q->active()->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now());
            //     })
            //     ->whereHas('redeemretailers', function($q) use($retailer)
            //     {
            //         $q->where('promotion_retailer_redeem', $retailer->merchant_id);
            //     })->active()->where('promotion_type', 'product')->first();

            $this->response->message = 'success';
            $this->response->data = $coupons;
            // dd($coupons);
            // $activityPageNotes = sprintf('Popup viewed: %s', 'Product Coupon');
            // $activityPage->setUser($user)
            //                 ->setActivityName('view_popup_product_coupon')
            //                 ->setActivityNameLong('View (Product Coupon Popup)')
            //                 ->setObject(null)
            //                 ->setModuleName('Coupon')
            //                 ->setNotes($activityPageNotes)
            //                 ->responseOK()
            //                 ->save();

            return $this->render();
        } catch (Exception $e) {
            // $activityPageNotes = sprintf('Failed to view Popup: %s', 'Product Coupon');
            // $activityPage->setUser($user)
            //                 ->setActivityName('view_popup_product_coupon')
            //                 ->setActivityNameLong('View (Product Coupon Popup) Failed')
            //                 ->setObject(null)
            //                 ->setModuleName('Coupon')
            //                 ->setNotes($activityPageNotes)
            //                 ->responseFailed()
            //                 ->save();
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

            $coupon = Coupon::active()->where('promotion_id', $promotion_id)->first();

            $this->response->message = 'success';
            $this->response->data = $coupon;

            // $activityPageNotes = sprintf('Popup viewed: %s', 'Cart Product Coupon');
            // $activityPage->setUser($user)
            //                 ->setActivityName('view_popup__cart_product_coupon')
            //                 ->setActivityNameLong('View (Cart Product Coupon Popup)')
            //                 ->setObject(null)
            //                 ->setNotes($activityPageNotes)
            //                 ->responseOK()
            //                 ->save();


            return $this->render();
        } catch (Exception $e) {
            // $activityPageNotes = sprintf('Failed to view Popup: %s', 'Cart Product Coupon');
            // $activityPage->setUser($user)
            //                 ->setActivityName('view_popup_cart_product_coupon')
            //                 ->setActivityNameLong('View (Cart Product Coupon Popup) Failed')
            //                 ->setObject(null)
            //                 ->setNotes($activityPageNotes)
            //                 ->responseFailed()
            //                 ->save();
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

            $cartdata = $this->cartCalc($user, $retailer);
            // dd($vat);
            // print_r($cartdata);
            $from = OrbitInput::get('from', null);
            if (empty($from)) {
                $activityPageNotes = sprintf('Page viewed : %s', 'Cart');
                $activityPage->setUser($user)
                                ->setActivityName('view_cart')
                                ->setActivityNameLong('View Cart')
                                ->setObject(null)
                                ->setModuleName('Cart')
                                ->setNotes($activityPageNotes)
                                ->responseOK()
                                ->save();
            }

            return View::make('mobile-ci.cart', array('page_title'=>Lang::get('mobileci.page_title.cart'), 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata));
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view : %s', 'Cart');
            $activityPage->setUser($user)
                            ->setActivityName('view_cart')
                            ->setActivityNameLong('View Cart Failed')
                            ->setObject(null)
                            ->setModuleName('Cart')
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
                            ->setActivityName('view_transfer_cart')
                            ->setActivityNameLong('View Transfer Cart')
                            ->setObject($cartdata->cart)
                            ->setModuleName('Cart')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.transfer-cart', array('page_title'=>Lang::get('mobileci.page_title.transfercart'), 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata));
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view: %s', 'Transfer Cart');
            $activityPage->setUser($user)
                            ->setActivityName('view_transfer_cart')
                            ->setActivityNameLong('View Transfer Cart Failed')
                            ->setObject(null)
                            ->setModuleName('Cart')
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getPaymentView()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartitems = $this->getCartForToolbar();

            $cartdata = $this->cartCalc($user, $retailer);

            if (! empty($cartitems)) {
                return View::make('mobile-ci.payment', array('page_title'=>Lang::get('mobileci.page_title.payment'), 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata));
            } else {
                return \Redirect::to('/customer/home');
            }

            $activityPageNotes = sprintf('Page viewed: %s', 'Online Payment');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_online_payment')
                            ->setActivityNameLong('View (Online Payment Page)')
                            ->setObject(null)
                            ->setModuleName('Cart')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Online Payment');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_online_payment')
                            ->setActivityNameLong('View (Online Payment) Failed')
                            ->setObject(null)
                            ->setModuleName('Cart')
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    // thank you from transfer cart
    public function getThankYouView()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $activityPageNotes = sprintf('Page viewed: %s', 'Thank You Page');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_thank_you')
                            ->setActivityNameLong('View (Thank You Page)')
                            ->setObject(null)
                            ->setModuleName('Cart')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            return View::make('mobile-ci.thankyoucart', array('retailer' => $retailer));
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Thank You Page');
            $activityPage->setUser($user)
                            ->setActivityName('view_page_thank_you')
                            ->setActivityNameLong('View (Thank You Page) Failed')
                            ->setObject(null)
                            ->setModuleName('Cart')
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();

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
                            ->setActivityType('add');
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

            $cartdetail = CartDetail::active()->where('product_id', $product_id)->where('product_variant_id', $product_variant_id)->where('cart_id', $cart->cart_id)->first();
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

            $activityPromoObj = null;
            $temp_price = $variant_price;
            foreach ($promo_products as $promo) {
                if($promo->promotionrule->rule_type == 'product_discount_by_percentage') {
                    $discount = $promo->promotionrule->discount_value * $variant_price;
                    if ($temp_price < $discount) {
                        $discount = $temp_price;
                    }
                    $price_after_promo = $price_after_promo - $discount;
                } elseif($promo->promotionrule->rule_type == 'product_discount_by_value') {
                    $discount = $promo->promotionrule->discount_value;
                    if ($temp_price < $discount) {
                        $discount = $temp_price;
                    }
                    $price_after_promo = $price_after_promo - $discount;
                } elseif($promo->promotionrule->rule_type == 'new_product_price') {
                    $new_price = $promo->promotionrule->discount_value;
                    $discount = $variant_price - $new_price;
                    if ($temp_price < $discount) {
                        $discount = $temp_price;
                    }
                    $price_after_promo = $price_after_promo - $discount;
                }
                $activityPromoObj = $promo;
                
                $temp_price = $temp_price - $discount;
            }

            $activityCoupon = array();
            $activityCouponObj = null;

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

                $used_coupons = IssuedCoupon::active()->where('issued_coupon_id', $coupon)->first();
                $activityCouponObj = $used_coupons->coupon;
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
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y" and p.status = "active"
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
                            ->setProduct($product)
                            ->setPromotion($activityPromoObj)
                            ->setCoupon($activityCouponObj)
                            ->setModuleName('Cart')
                            ->setNotes($activityCartNotes)
                            ->responseOK()
                            ->save();

            foreach ($promo_products as $promo) {
                $activityChild = Activity::parent($activityCart)
                                    ->setObject($promo)
                                    ->setPromotion($promo)
                                    ->setCoupon(null)
                                    ->setUser($user)
                                    ->setEvent(null)
                                    ->setNotes($promo->promotion_name)
                                    ->responseOK()
                                    ->save();
            }

            foreach ($activityCoupon as $_coupon) {
                $activityChild = Activity::parent($activityCart)
                                    ->setObject($_coupon->coupon)
                                    ->setCoupon($_coupon->coupon)
                                    ->setPromotion(null)
                                    ->setEvent(null)
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
                            ->setModuleName('Cart')
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
        $used_coupon_id = null;
        $activityCart = Activity::mobileci()
                            ->setActivityType('add');
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

            $cartdetail = CartDetail::active()->where('product_id', $product_id)->where('product_variant_id', $product_variant_id)->where('cart_id', $cart->cart_id)->first();
            
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

                    $used_coupons = IssuedCoupon::active()->where('issued_coupon_id', $coupon)->first();

                    $cartcoupon = new CartCoupon;
                    $cartcoupon->issued_coupon_id = $coupon;
                    $cartcoupon->object_type = 'cart_detail';
                    $cartcoupon->object_id = $cartdetail->cart_detail_id;
                    $cartcoupon->save();
                    $used_coupons->status = 'deleted';
                    $used_coupons->save();
                    $activityCoupon[] = $used_coupons->coupon;
                }
                
                $this->response->message = 'success';
                $this->response->data = $cartdetail;
                
                foreach ($activityCoupon as $_coupon) {
                    $used_coupon_id = $used_coupons->promotion_id;
                    $activityCartNotes = sprintf('Use Coupon : %s', $used_coupon_id);
                    $activityCart->setUser($user)
                                    ->setActivityName('use_coupon')
                                    ->setActivityNameLong('Use Coupon')
                                    ->setObject($_coupon)
                                    ->setCoupon($_coupon)
                                    ->setModuleName('Coupon')
                                    ->setNotes($activityCartNotes)
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
            $activityCartNotes = sprintf('Use Coupon Failed : %s', $used_coupon_id);
            $activityCart->setUser($user)
                            ->setActivityName('use_coupon')
                            ->setActivityNameLong('Add Coupon To Cart Failed')
                            ->setObject(null)
                            ->setModuleName('Coupon')
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
                        ->setActivityType('add');
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

            $used_coupons = IssuedCoupon::active()->where('issued_coupon_id', $couponid)->first();
            
            $cartcoupon = new CartCoupon;
            $cartcoupon->issued_coupon_id = $couponid;
            $cartcoupon->object_type = 'cart';
            $cartcoupon->object_id = $cart->cart_id;
            $cartcoupon->save();
            
            $used_coupons->status = 'deleted';
            $used_coupons->save();

            $this->response->message = 'success';

            $activityPageNotes = sprintf('Use Coupon : %s', $couponid);
            $activityPage->setUser($user)
                            ->setActivityName('use_coupon')
                            ->setActivityNameLong('Use Coupon')
                            ->setObject($used_coupons->coupon)
                            ->setCoupon($used_coupons->coupon)
                            ->setModuleName('Coupon')
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
                            ->setModuleName('Coupon')
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
        $productid = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('delete');
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
            
            $cartdetail = CartDetail::where('cart_detail_id', $cartdetailid)->active()->first();
            
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

            $cart = Cart::where('cart_id', $cartdetail->cart_id)->active()->first();

            $quantity = $cartdetail->quantity;
            $cart->total_item = $cart->total_item - $quantity;
            
            $cart->save();

            $cartdetail->delete();
            
            $cartdata = new stdclass();
            $cartdata->cart = $cart;
            $this->response->message = 'success';
            $this->response->data = $cartdata;
            $productid = $cartdetail->product->product_id;
            $activityPageNotes = sprintf('Deleted product from cart. Product id: %s', $productid);
            $activityPage->setUser($user)
                            ->setActivityName('delete_cart')
                            ->setActivityNameLong('Delete Product From Cart')
                            ->setObject($cartdetail)
                            ->setProduct($cartdetail->product)
                            ->setModuleName('Cart')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            $this->commit();
            return $this->render();

        } catch (Exception $e) {
            $this->rollback();
            $activityPageNotes = sprintf('Failed to delete from cart. Product id: %s', $productid);
            $activityPage->setUser($user)
                            ->setActivityName('delete_cart')
                            ->setActivityNameLong('Failed To Delete From Cart')
                            ->setObject(null)
                            ->setModuleName('Cart')
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
                        ->setActivityType('delete');
        try {
            $this->registerCustomValidation();

            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $issuedcouponid = OrbitInput::post('detail');

            $this->beginTransaction();
            
            $cartcoupon = CartCoupon::whereHas('issuedcoupon', function($q) use($user, $issuedcouponid)
            {
                $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.issued_coupon_id', $issuedcouponid);
            })->first();

            if(!empty($cartcoupon)) {
                $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $cartcoupon->issued_coupon_id)->first();
                $issuedcoupon->makeActive();
                $issuedcoupon->save();
                $couponid = $issuedcoupon->coupon->promotion_id;
                $cartcoupon->delete(TRUE);
            }

            $this->response->message = 'success';

            $activityPageNotes = sprintf('Delete Coupon From Cart. Coupon Id: %s', $couponid);
            $activityPage->setUser($user)
                            ->setActivityName('delete_cart')
                            ->setActivityNameLong('Delete Coupon From Cart')
                            ->setObject($issuedcoupon->coupon)
                            ->setCoupon($issuedcoupon->coupon)
                            ->setModuleName('Cart')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

            $this->commit();
            return $this->render();

        } catch (Exception $e) {
            $this->rollback();
            $activityPageNotes = sprintf('Failed To Delete Coupon From Cart. Coupon Id: %s', $couponid);
            $activityPage->setUser($user)
                            ->setActivityName('delete_cart')
                            ->setActivityNameLong('Failed To Delete Coupon From Cart')
                            ->setObject(null)
                            ->setModuleName('Cart')
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
                        ->setActivityType('update');
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
            $cart = Cart::where('cart_id', $cartdetail->cart_id)->active()->first();

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
                            ->setActivityName('update_cart')
                            ->setActivityNameLong('Update Cart')
                            ->setObject($cartdetail)
                            ->setProduct($product)
                            ->setModuleName('Cart')
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
                            ->setModuleName('Cart')
                            ->setNotes($activityPageNotes)
                            ->responseFailed()
                            ->save();
            return $e;
        }
    }

    public function postSaveTransaction()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('payment');
        $user = null;
        $activity_payment = null;
        $activity_payment_label = null;
        $transaction = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartdata = $this->cartCalc($user, $retailer);

            // $total_item = trim(OrbitInput::post('total_item'));
            // $subtotal = trim(OrbitInput::post('subtotal'));
            // $vat = trim(OrbitInput::post('vat'));
            $total_to_pay = $cartdata->cartsummary->total_to_pay;
            // $tendered         = trim(OrbitInput::post('tendered'));
            // $change           = trim(OrbitInput::post('change'));
            $merchant_id = $retailer->parent->merchant_id;
            $retailer_id = $retailer->merchant_id;
            // $cashier_id       = trim(OrbitInput::post('cashier_id'));
            $customer_id = $user->user_id;
            $payment_method = 'online_payment';
            $cart = $cartdata->cart; //data of array
            $cartdetails = $cartdata->cartdetails; //data of array

            // $cart_promotion   = OrbitInput::post('cart_promotion'); // data of array
            // $cart_coupon      = OrbitInput::post('cart_coupon'); // data of array
            $cart_id = null;

            $activity_payment = 'payment_online';
            $activity_payment_label = 'Payment Online';

            // Begin database transaction
            $this->beginTransaction();

            // update last spent user
            $userdetail = UserDetail::where('user_id', $user->user_id)->first();
            $userdetail->last_spent_any_shop = $cartdata->cartsummary->total_to_pay;
            $userdetail->last_spent_shop_id = $retailer->merchant_id;
            $userdetail->save();

            // insert to table transaction
            $transaction = new Transaction();
            $transaction->total_item     = $cartdata->cart->total_item;
            if ($retailer->parent->vat_included == 'yes') {
                $transaction->subtotal = $cartdata->cartsummary->total_to_pay;
            } else {
                $transaction->subtotal = $cartdata->cartsummary->subtotal_wo_tax;
            }
            $transaction->vat            = $cartdata->cartsummary->vat;
            $transaction->total_to_pay   = $cartdata->cartsummary->total_to_pay;
            $transaction->tendered       = $cartdata->cartsummary->total_to_pay;
            $transaction->change         = 0;
            $transaction->merchant_id    = $merchant_id;
            $transaction->retailer_id    = $retailer_id;
            $transaction->cashier_id     = NULL;
            $transaction->customer_id    = $customer_id;
            $transaction->payment_method = $payment_method;
            $transaction->status         = 'paid';

            $transaction->save();

            //insert to table transaction_details
            foreach($cartdetails as $cart_value){
                $cart_id = $cart_value->cart->cart_id;
                $transactiondetail = new TransactionDetail;
                $transactiondetail->transaction_id              = $transaction->transaction_id;
                $transactiondetail->product_id                  = $cart_value->product_id;
                $transactiondetail->product_name                = $cart_value->product->product_name;
                $transactiondetail->product_code                = $cart_value->product->product_code;
                $transactiondetail->quantity                    = $cart_value->quantity;
                $transactiondetail->upc                         = $cart_value->product->upc_code;
                $transactiondetail->price                       = $cart_value->product->price;
                
                if(!empty($cart_value->variant)){
                    $transactiondetail->product_variant_id          = $cart_value->variant->product_variant_id;
                    $transactiondetail->variant_price               = $cart_value->variant->price;
                    $transactiondetail->variant_upc                 = $cart_value->variant->upc;
                    $transactiondetail->variant_sku                 = $cart_value->variant->sku;

                    if (!empty($cart_value->variant->product_attribute_value_id1)) {
                        $transactiondetail->product_attribute_value_id1 = $cart_value->variant->product_attribute_value_id1;
                    }
                    if(!empty($cart_value->variant->product_attribute_value_id2)) {
                        $transactiondetail->product_attribute_value_id2 = $cart_value->variant->product_attribute_value_id2;
                    }
                    if(!empty($cart_value->variant->product_attribute_value_id3)) {
                        $transactiondetail->product_attribute_value_id3 = $cart_value->variant->product_attribute_value_id3;
                    }
                    if(!empty($cart_value->variant->product_attribute_value_id4)) {
                        $transactiondetail->product_attribute_value_id4 = $cart_value->variant->product_attribute_value_id4;
                    }
                    if(!empty($cart_value->variant->product_attribute_value_id5)) {
                        $transactiondetail->product_attribute_value_id5 = $cart_value->variant->product_attribute_value_id5;
                    }

                    if(!empty($cart_value->variant->attributeValue1->value)) {
                        $transactiondetail->product_attribute_value1 = $cart_value->variant->attributeValue1->value;
                    }
                    if(!empty($cart_value->variant->attributeValue2->value)) {
                        $transactiondetail->product_attribute_value2 = $cart_value->variant->attributeValue2->value;
                    }
                    if(!empty($cart_value->variant->attributeValue3->value)) {
                        $transactiondetail->product_attribute_value3 = $cart_value->variant->attributeValue3->value;
                    }
                    if(!empty($cart_value->variant->attributeValue4->value)) {
                        $transactiondetail->product_attribute_value4 = $cart_value->variant->attributeValue4->value;
                    }
                    if(!empty($cart_value->variant->attributeValue5->value)) {
                        $transactiondetail->product_attribute_value5 = $cart_value->variant->attributeValue5->value;
                    }

                    if(!empty($cart_value->variant->attributeValue1->attribute->product_attribute_name)) {
                         $transactiondetail->product_attribute_name1 = $cart_value->variant->attributeValue1->attribute->product_attribute_name;
                    }
                    if(!empty($cart_value->variant->attributeValue2->attribute->product_attribute_name)) {
                         $transactiondetail->product_attribute_name2 = $cart_value->variant->attributeValue2->attribute->product_attribute_name;
                    }
                    if(!empty($cart_value->variant->attributeValue3->attribute->product_attribute_name)) {
                         $transactiondetail->product_attribute_name3 = $cart_value->variant->attributeValue3->attribute->product_attribute_name;
                    }
                    if(!empty($cart_value->variant->attributeValue4->attribute->product_attribute_name)) {
                         $transactiondetail->product_attribute_name4 = $cart_value->variant->attributeValue4->attribute->product_attribute_name;
                    }
                    if(!empty($cart_value->variant->attributeValue5->attribute->product_attribute_name)) {
                         $transactiondetail->product_attribute_name5 = $cart_value->variant->attributeValue5->attribute->product_attribute_name;
                    }
                }

                if(!empty($cart_value->tax1->merchant_tax_id)) {
                    $transactiondetail->merchant_tax_id1 = $cart_value->tax1->merchant_tax_id;
                }
                if(!empty($cart_value->tax2->merchant_tax_id)) {
                    $transactiondetail->merchant_tax_id2 = $cart_value->tax2->merchant_tax_id;
                }

                // dd($cart_value->product->attribute4);
                if(! is_null($cart_value->product->attribute1)) {
                    $transactiondetail->attribute_id1 = $cart_value->product->attribute1->product_attribute_id;
                }
                if(! is_null($cart_value->product->attribute2)) {
                    $transactiondetail->attribute_id2 = $cart_value->product->attribute2->product_attribute_id;
                }
                if(! is_null($cart_value->product->attribute3)) {
                    $transactiondetail->attribute_id3 = $cart_value->product->attribute3->product_attribute_id;
                }
                if(! is_null($cart_value->product->attribute4)) {
                    $transactiondetail->attribute_id4 = $cart_value->product->attribute4->product_attribute_id;
                }
                if(! is_null($cart_value->product->attribute5)) {
                    $transactiondetail->attribute_id5 = $cart_value->product->attribute5->product_attribute_id;
                }

                $transactiondetail->save();

                // product based promotion
                if(!empty($cart_value->promo_for_this_product)){
                    foreach ($cart_value->promo_for_this_product as $value) {
                        // dd($value);
                        $transactiondetailpromotion = new TransactionDetailPromotion;
                        $transactiondetailpromotion->transaction_detail_id = $transactiondetail->transaction_detail_id;
                        $transactiondetailpromotion->transaction_id = $transaction->transaction_id;
                        $transactiondetailpromotion->promotion_id = $value->promotion_id;
                        $transactiondetailpromotion->promotion_name = $value->promotion_name;

                        if(!empty($value->promotion_type)){
                            $transactiondetailpromotion->promotion_type = $value->promotion_type;
                        } else {
                            // $transactiondetailpromotion->promotion_type = $value->promotion_detail->promotion_type;
                        }
                        
                        $transactiondetailpromotion->rule_type = $value->rule_type;

                        if(!empty($value->rule_value)){
                            $transactiondetailpromotion->rule_value = $value->rule_value;
                        } else {
                            // $transactiondetailpromotion->rule_value = $value->promotion_detail->rule_value;
                        }

                        if(!empty($value->discount_object_type)){
                            $transactiondetailpromotion->discount_object_type = $value->discount_object_type;
                        } else {
                            // $transactiondetailpromotion->discount_object_type = $value->promotion_detail->discount_object_type;
                        }

                        $transactiondetailpromotion->discount_value = $value->discount_value;
                        $transactiondetailpromotion->value_after_percentage = $value->discount;

                        if(!empty($value->description)){
                            $transactiondetailpromotion->description = $value->description;
                        } else {
                            // $transactiondetailpromotion->description = $value->promotion_detail->description;
                        }

                        if(!empty($value->begin_date)){
                            $transactiondetailpromotion->begin_date = $value->begin_date;
                        } else {
                            // $transactiondetailpromotion->begin_date = $value->promotion_detail->begin_date;
                        }

                        if(!empty($value->end_date)){
                            $transactiondetailpromotion->end_date = $value->end_date;
                        } else {
                            // $transactiondetailpromotion->end_date = $value->promotion_detail->end_date;
                        }

                        $transactiondetailpromotion->save();
                        
                    }
                }


                // product based coupon
                if(!empty($cart_value->coupon_for_this_product)){
                    foreach ($cart_value->coupon_for_this_product as $value) {
                            $transactiondetailcoupon = new TransactionDetailCoupon;
                            $transactiondetailcoupon->transaction_detail_id = $transactiondetail->transaction_detail_id;
                            $transactiondetailcoupon->transaction_id = $transaction->transaction_id;
                            $transactiondetailcoupon->promotion_id = $value->issuedcoupon->issued_coupon_id;
                            $transactiondetailcoupon->promotion_name = $value->issuedcoupon->coupon->promotion_name;
                            $transactiondetailcoupon->promotion_type = $value->issuedcoupon->coupon->promotion_type;
                            $transactiondetailcoupon->rule_type = $value->issuedcoupon->rule_type;
                            $transactiondetailcoupon->rule_value = $value->issuedcoupon->rule_value;
                            $transactiondetailcoupon->category_id1 = $value->issuedcoupon->rule_object_id1;
                            $transactiondetailcoupon->category_id2 = $value->issuedcoupon->rule_object_id2;
                            $transactiondetailcoupon->category_id3 = $value->issuedcoupon->rule_object_id3;
                            $transactiondetailcoupon->category_id4 = $value->issuedcoupon->rule_object_id4;
                            $transactiondetailcoupon->category_id5 = $value->issuedcoupon->rule_object_id5;
                            $transactiondetailcoupon->category_name1 = $value->issuedcoupon->discount_object_id1;
                            $transactiondetailcoupon->category_name2 = $value->issuedcoupon->discount_object_id2;
                            $transactiondetailcoupon->category_name3 = $value->issuedcoupon->discount_object_id3;
                            $transactiondetailcoupon->category_name4 = $value->issuedcoupon->discount_object_id4;
                            $transactiondetailcoupon->category_name5 = $value->issuedcoupon->discount_object_id5;
                            $transactiondetailcoupon->discount_object_type = $value->issuedcoupon->discount_object_type;
                            $transactiondetailcoupon->discount_value = $value->discount_value;
                            $transactiondetailcoupon->value_after_percentage = $value->discount;
                            $transactiondetailcoupon->coupon_redeem_rule_value = $value->issuedcoupon->coupon_redeem_rule_value;
                            $transactiondetailcoupon->description = $value->issuedcoupon->description;
                            $transactiondetailcoupon->begin_date = $value->issuedcoupon->begin_date;
                            $transactiondetailcoupon->end_date = $value->issuedcoupon->end_date;
                            $transactiondetailcoupon->save();

                            // coupon redeemed
                            if(!empty($value->issuedcoupon->issued_coupon_id)){
                                $coupon_id = intval($value->issuedcoupon->issued_coupon_id);
                                $coupon_redeemed = IssuedCoupon::where('issued_coupon_id', $coupon_id)->update(array('status' => 'redeemed'));
                            }
                    }
                }

                // transaction detail taxes
                if(!empty($cartdata->cartsummary->taxes)){
                    foreach ($cartdata->cartsummary->taxes as $value) {
                        $transactiondetailtax = new TransactionDetailTax;
                        $transactiondetailtax->transaction_detail_id = $transactiondetail->transaction_detail_id;
                        $transactiondetailtax->transaction_id = $transaction->transaction_id;
                        $transactiondetailtax->tax_name = $value->tax_name;
                        $transactiondetailtax->tax_value = $value->tax_value;
                        $transactiondetailtax->tax_order = $value->tax_order;
                        $transactiondetailtax->save();
                    }
                }
            }


            // cart based promotion
            if(!empty($cart_promotion)){
                foreach ($cart_promotion as $value) {    
                    $transactiondetailpromotion = new TransactionDetailPromotion;
                    $transactiondetailpromotion->transaction_detail_id = $transactiondetail->transaction_detail_id;
                    $transactiondetailpromotion->transaction_id = $transaction->transaction_id;
                    $transactiondetailpromotion->promotion_id = $value->promotion_id;
                    $transactiondetailpromotion->promotion_name = $value->promotion_name;
                    $transactiondetailpromotion->promotion_type = $value->promotion_type;
                    $transactiondetailpromotion->rule_type = $value->promotionrule->rule_type;
                    $transactiondetailpromotion->rule_value = $value->promotionrule->rule_value;
                    $transactiondetailpromotion->discount_object_type = $value->promotionrule->discount_object_type;
                    if ($value->promotionrule->rule_type=="cart_discount_by_percentage") {
                        $discount_percent = intval($value->promotionrule->discount)/100;
                        $discount_value = $this->removeFormat($value->promotionrule->discount_value);
                        $transactiondetailpromotion->discount_value = $discount_percent;
                        $transactiondetailpromotion->value_after_percentage = $discount_value;
                    } elseif ($value->promotionrule->rule_type=="cart_discount_by_value") {
                        $discount_value = $this->removeFormat($value->promotionrule->discount_value);
                        $transactiondetailpromotion->discount_value = $discount_value;
                        $transactiondetailpromotion->value_after_percentage = $discount_value;
                    }
                    $transactiondetailpromotion->description = $value->description;
                    $transactiondetailpromotion->begin_date = $value->begin_date;
                    $transactiondetailpromotion->end_date = $value->end_date;
                    $transactiondetailpromotion->save();
                
                }
            }


            // cart based coupon
            if(!empty($cart_coupon)){
                foreach ($cart_coupon as $value) {
                    
                    $transactiondetailcoupon = new TransactionDetailCoupon();
                    $transactiondetailcoupon->transaction_detail_id = $transactiondetail->transaction_detail_id;
                    $transactiondetailcoupon->transaction_id = $transaction->transaction_id;
                    $transactiondetailcoupon->promotion_id = $value->issuedcoupon->issued_coupon_id;
                    $transactiondetailcoupon->promotion_name = $value->issuedcoupon->promotion_name;
                    $transactiondetailcoupon->promotion_type = $value->issuedcoupon->promotion_type;
                    $transactiondetailcoupon->rule_type = $value->issuedcoupon->rule_type;
                    $transactiondetailcoupon->rule_value = $value->issuedcoupon->rule_value;
                    $transactiondetailcoupon->category_id1 = $value->issuedcoupon->rule_object_id1;
                    $transactiondetailcoupon->category_id2 = $value->issuedcoupon->rule_object_id2;
                    $transactiondetailcoupon->category_id3 = $value->issuedcoupon->rule_object_id3;
                    $transactiondetailcoupon->category_id4 = $value->issuedcoupon->rule_object_id4;
                    $transactiondetailcoupon->category_id5 = $value->issuedcoupon->rule_object_id5;
                    $transactiondetailcoupon->category_name1 = $value->issuedcoupon->discount_object_id1;
                    $transactiondetailcoupon->category_name2 = $value->issuedcoupon->discount_object_id2;
                    $transactiondetailcoupon->category_name3 = $value->issuedcoupon->discount_object_id3;
                    $transactiondetailcoupon->category_name4 = $value->issuedcoupon->discount_object_id4;
                    $transactiondetailcoupon->category_name5 = $value->issuedcoupon->discount_object_id5;
                    $transactiondetailcoupon->discount_object_type = $value->issuedcoupon->discount_object_type;
                    if($value->issuedcoupon->rule_type=="cart_discount_by_percentage"){
                        $discount_percent = intval($value->issuedcoupon->discount)/100;
                        $discount_value = $this->removeFormat($value->issuedcoupon->discount_value);
                        $transactiondetailcoupon->discount_value = $discount_percent;
                        $transactiondetailcoupon->value_after_percentage = $discount_value;
                    } else {
                        $discount_value = $this->removeFormat($value->issuedcoupon->discount_value);
                        $transactiondetailcoupon->discount_value = $discount_percent;
                        $transactiondetailcoupon->value_after_percentage = $discount_value;
                    }
                    $transactiondetailcoupon->coupon_redeem_rule_value = $value->issuedcoupon->coupon_redeem_rule_value;
                    $transactiondetailcoupon->description = $value->issuedcoupon->description;
                    $transactiondetailcoupon->begin_date = $value->issuedcoupon->begin_date;
                    $transactiondetailcoupon->end_date = $value->issuedcoupon->end_date;
                    $transactiondetailcoupon->save();

                    // coupon redeemed
                    if(!empty($value->issuedcoupon->issued_coupon_id)){
                        $coupon_id = intval($value->issuedcoupon->issued_coupon_id);
                        $coupon_redeemed = IssuedCoupon::where('issued_coupon_id', $coupon_id)->update(array('status' => 'redeemed'));
                    }
                
                }
            }

            // issue product based coupons (if any)
            $acquired_coupons = array();
            if (! empty($customer_id)) {
                foreach ($cartdetails as $v) {
                    $product_id = $v->product_id;
                    
                    $coupons = DB::select(DB::raw('SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                    inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                    inner join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
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
                    WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND prod.product_id = :productid '), array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product_id));
                    
                    // dd($coupons);
                    if ($coupons!=NULL) {
                        foreach ($coupons as $c) {
                            $issued = IssuedCoupon::where('promotion_id', $c->promotion_id)->count();
                            // dd($issued);
                            if ($issued <= $c->maximum_issued_coupon) {
                                $issue_coupon = new IssuedCoupon;
                                $issue_coupon->promotion_id = $c->promotion_id;
                                $issue_coupon->issued_coupon_code = '';
                                $issue_coupon->user_id = $customer_id;
                                $issue_coupon->expired_date = Carbon::now()->addDays($c->coupon_validity_in_days);
                                $issue_coupon->issued_date = Carbon::now();
                                $issue_coupon->issuer_retailer_id = $retailer->merchant_id;
                                $issue_coupon->status = 'active';
                                $issue_coupon->save();
                                $issue_coupon->issued_coupon_code = IssuedCoupon::ISSUE_COUPON_INCREMENT+$issue_coupon->issued_coupon_id;
                                $issue_coupon->save();

                                $acquired_coupon = IssuedCoupon::with('coupon', 'coupon.couponrule', 'coupon.redeemretailers')->where('issued_coupon_id', $issue_coupon->issued_coupon_id)->first();
                                $acquired_coupons[] = $acquired_coupon;
                            }
                        }  
                    }
                }
            }

            // issue cart based coupons (if any)
            if(! empty($customer_id)){
                $coupon_carts = Coupon::join('promotion_rules', function($q) use($total_to_pay)
                {
                    $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.rule_value', '<=', $total_to_pay);
                })->active()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function($q) use ($retailer)
                {
                    $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                })
                ->get();
                // dd($coupon_carts);
                if(!empty($coupon_carts)){
                    foreach($coupon_carts as $kupon){
                        $issued = IssuedCoupon::where('promotion_id', $kupon->promotion_id)->count();
                        // dd($issued);
                        if ($issued <= $kupon->maximum_issued_coupon) {
                            $issue_coupon = new IssuedCoupon;
                            $issue_coupon->promotion_id = $kupon->promotion_id;
                            $issue_coupon->issued_coupon_code = '';
                            $issue_coupon->user_id = $customer_id;
                            $issue_coupon->expired_date = Carbon::now()->addDays($kupon->coupon_validity_in_days);
                            $issue_coupon->issued_date = Carbon::now();
                            $issue_coupon->issuer_retailer_id = $retailer->merchant_id;
                            $issue_coupon->status = 'active';
                            $issue_coupon->save();
                            $issue_coupon->issued_coupon_code = IssuedCoupon::ISSUE_COUPON_INCREMENT+$issue_coupon->issued_coupon_id;
                            $issue_coupon->save();

                            $acquired_coupon = IssuedCoupon::with('coupon', 'coupon.couponrule', 'coupon.redeemretailers')->where('issued_coupon_id', $issue_coupon->issued_coupon_id)->first();
                            $acquired_coupons[] = $acquired_coupon;
                        }
                    }
                }
            }

            // delete the cart
            // if(! empty($cart_id)){
            //     $cart_delete = Cart::where('status', 'active')->where('cart_id', $cart_id)->first();
            //     $cart_delete->delete();
            //     $cart_delete->save();
            //     $cart_detail_delete = CartDetail::where('status', 'active')->where('cart_id', $cart_id)->update(array('status' => 'deleted'));
            // }
            

            $this->response->data = $transaction;
            $this->commit();

            $activityPageNotes = sprintf('Transaction Success. Cart Id : %s', $cartdata->cart->cart_id);
            $activity->setUser($user)
                    ->setActivityName($activity_payment)
                    ->setActivityNameLong($activity_payment_label . ' Success')
                    ->setObject($transaction)
                    ->setModuleName('Cart')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();

            return View::make('mobile-ci.thankyou', array('retailer'=>$retailer, 'cartdata' => $cartdata, 'transaction' => $transaction, 'acquired_coupons' => $acquired_coupons));

        } catch (Exception $e) {
            // $activityPageNotes = sprintf('Failed to view Page: %s', 'Category');
            $this->rollback();
            $activity->setUser($user)
                            ->setActivityName($activity_payment)
                            ->setActivityNameLong($activity_payment . ' Failed')
                            ->setObject(null)
                            ->setModuleName('Cart')
                            ->setNotes($e->getMessage())
                            ->responseFailed()
                            ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postEventPopUpActivity()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = null;
        $event_id = null;
        $event = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $event_id = OrbitInput::post('eventdata');
            $event = EventModel::active()->where('event_id', $event_id)->first();

            $activityNotes = sprintf('Event Click. Event Id : %s', $event_id);
            $activity->setUser($user)
                    ->setActivityName('event_click')
                    ->setActivityNameLong('Event Click')
                    ->setObject($event)
                    ->setModuleName('Event')
                    ->setEvent($event)
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
        } catch (Exception $e) {
            $this->rollback();
            $activityNotes = sprintf('Event Click Failed. Event Id : %s', $event_id);
            $activity->setUser($user)
                    ->setActivityName('event_click')
                    ->setActivityNameLong('Event Click Failed')
                    ->setObject(null)
                    ->setModuleName('Event')
                    ->setEvent($event)
                    ->setNotes($e->getMessage())
                    ->responseFailed()
                    ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postDisplayEventPopUpActivity()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('view');
        $user = null;
        $event_id = null;
        $event = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $event_id = OrbitInput::post('eventdata');
            $event = EventModel::active()->where('event_id', $event_id)->first();

            $activityNotes = sprintf('Event View. Event Id : %s', $event_id);
            $activity->setUser($user)
                    ->setActivityName('event_view')
                    ->setActivityNameLong('Event View (Pop Up)')
                    ->setObject($event)
                    ->setModuleName('Event')
                    ->setEvent($event)
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
        } catch (Exception $e) {
            $this->rollback();
            $activityNotes = sprintf('Event Click Failed. Event Id : %s', $event_id);
            $activity->setUser($user)
                    ->setActivityName('event_click')
                    ->setActivityNameLong('Event Click Failed')
                    ->setObject(null)
                    ->setModuleName('Event')
                    ->setEvent($event)
                    ->setNotes($e->getMessage())
                    ->responseFailed()
                    ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postClickWidgetActivity()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = null;
        $widget_id = null;
        $widget = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $widget_id = OrbitInput::post('widgetdata');
            $widget = Widget::active()->where('widget_id', $widget_id)->first();

            $activityNotes = sprintf('Widget Click. Widget Id : %s', $widget_id);
            $activity->setUser($user)
                    ->setActivityName('widget_click')
                    ->setActivityNameLong('Widget Click ' . ucwords(str_replace('_', ' ', $widget->widget_type)))
                    ->setObject($widget)
                    ->setModuleName('Widget')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
        } catch (Exception $e) {
            
            $activityNotes = sprintf('Widget Click Failed. Widget Id : %s', $widget_id);
            $activity->setUser($user)
                    ->setActivityName('widget_click')
                    ->setActivityNameLong('Widget Click Failed')
                    ->setObject(null)
                    ->setModuleName('Widget')
                    ->setNotes($e->getMessage())
                    ->responseFailed()
                    ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postClickSaveReceiptActivity()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = null;
        $transaction_id = null;
        $transaction = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $transaction_id = OrbitInput::post('transactiondata');
            $transaction = Transaction::where('transaction_id', $transaction_id)
                ->where('customer_id', $user->user_id)
                ->where('status', 'paid')
                ->first();
            
            $activityNotes = sprintf('Save Receipt Click. Transaction Id : %s', $transaction_id);
            $activity->setUser($user)
                    ->setActivityName('save_receipt_click')
                    ->setActivityNameLong('Save Receipt Click')
                    ->setObject($transaction)
                    ->setModuleName('Cart')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
        } catch (Exception $e) {
            
            $activityNotes = sprintf('Save Receipt Click Failed. Transaction Id : %s', $transaction_id);
            $activity->setUser($user)
                    ->setActivityName('save_receipt_click')
                    ->setActivityNameLong('Save Receipt Click Failed')
                    ->setObject(null)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->responseFailed()
                    ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postClickCheckoutActivity()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = null;
        $cart_id = null;
        $cart = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartdata = $this->getCartData();
            $cart = $cartdata->cart;
            $cart_id = $cart->cart_id;

            $activityNotes = sprintf('Checkout. Cart Id : %s', $cart_id);
            $activity->setUser($user)
                    ->setActivityName('checkout')
                    ->setActivityNameLong('Checkout')
                    ->setObject($cart)
                    ->setModuleName('Cart')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
        } catch (Exception $e) {
            $activityNotes = sprintf('Checkout Failed. Cart Id : %s', $cart_id);
            $activity->setUser($user)
                    ->setActivityName('checkout')
                    ->setActivityNameLong('Checkout Failed')
                    ->setObject(null)
                    ->setModuleName('Cart')
                    ->setNotes($e->getMessage())
                    ->responseFailed()
                    ->save();
            // return $this->redirectIfNotLoggedIn($e);
            return $e;
        }
    }

    public function postSendTicket() 
    {
        try {
            $user = $this->getLoggedInUser();

            $ticketdata = OrbitInput::post('ticketdata');
            $transactionid = OrbitInput::post('transactionid');

            $transaction = Transaction::with('details.product')->where('transaction_id', $transactionid)->where('customer_id', $user->user_id)->first();

            $ticketdata = str_replace('data:image/png;base64,', '', $ticketdata);

            $image = base64_decode($ticketdata);

            $date = str_replace(' ', '_', $transaction->created_at);

            $filename = 'receipt-' . $date . '.png';
            // $ticketAttachment = Swift_Attachment::newInstance($image, $filename, 'image/png');

            $mailviews = array(
                'html' => 'emails.receipt.receipt-html',
                'text' => 'emails.receipt.receipt-text'
            );

            $retailer = $this->getRetailerInfo();

            \Mail::send($mailviews, array('user' => $user, 'retailer' => $retailer, 'transactiondetails' => $transaction->details), function($message) use($user, $image, $filename)
            {
                $message->to('sembarang@vm.orbit-shop.rio', $user->getFullName())->subject('Orbit Receipt');
                $message->attachData($image, $filename, array('mime' => 'image/png'));    
            });
            
        } catch (Exception $e) {
            return $e;
        }
    }

    protected function registerCustomValidation()
    {
        // Check user email address, it should not exists
        Validator::extend('orbit.email.exists', function ($attribute, $value, $parameters) {
            $user = User::active()
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
            $category = Category::active()
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
            $product = Product::active()
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
                }))->active()
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
                }))->active()
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
            $product = \ProductVariant::active()
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
                ->active()
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
            })->active()
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
            $config->setConfig('session_origin.header.name', 'X-Orbit-Mobile-Session');
            $config->setConfig('session_origin.query_string.name', 'orbit_mobile_session');
            $config->setConfig('session_origin.cookie.name', 'orbit_mobile_session');
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
            }))
            ->whereHas('product', function($q) {
                $q->where('products.status', 'active');
            })
            ->where('status', 'active')->where('cart_id', $cart->cart_id)->get();
            $cartdata = new stdclass();
            $cartdata->cart = $cart;
            $cartdata->cartdetails = $cartdetails;

            return $cartdata;
        } catch (Exception $e) {
            // return $this->redirectIfNotLoggedIn($e);
            return $e->getMessage();
        }
    }

    protected function cartCalc($user, $retailer)
    {
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
            }), 'tax1', 'tax2')
            ->active()
            ->where('cart_id', $cart->cart_id)
            ->whereHas('product', function($q) {
                $q->where('products.status', 'active');
            })
            ->get();

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
            $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.status', 'deleted');
        })->whereHas('cartdetail', function($q)
        {
            $q->where('cart_coupons.object_type', '=', 'cart_detail');
        })->get();
        // dd($used_product_coupons);

        $promo_carts = Promotion::with('promotionrule')->active()->where('is_coupon', 'N')->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('retailers', function($q) use ($retailer)
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
            ->where('issued_coupons.status', 'deleted')
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

        $taxes = \MerchantTax::active()->where('merchant_id', $retailer->parent_id)->get();
        
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
                        inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y" and p.status = "active"
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
                    $promo_for_this_product = clone $promo_filter;
                    if($promo_filter->rule_type == 'product_discount_by_percentage') {
                        $discount = $promo_filter->discount_value * $original_price * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value * 100;
                    } elseif($promo_filter->rule_type == 'product_discount_by_value') {
                        $discount = $promo_filter->discount_value * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value;
                    } elseif ($promo_filter->rule_type == 'new_product_price') {
                        $discount = ($original_price - $promo_filter->discount_value) * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value;
                    }
                    $promo_for_this_product->promotion_id = $promo_filter->promotion_id;
                    $promo_for_this_product->promotion_name = $promo_filter->promotion_name;
                    $promo_for_this_product->rule_type = $promo_filter->rule_type;
                    $promo_for_this_product->discount = $discount;
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

                    $promo_vat = ($discount - $promo_wo_tax);
                    $vat = $vat - $promo_vat;
                    $promo_wo_tax = $promo_wo_tax;
                    $subtotal = $subtotal - $promo_for_this_product->discount;
                    $subtotal_wo_tax = $subtotal_wo_tax - $promo_wo_tax;
                    $promo_for_this_product_array[] = $promo_for_this_product;
                }
                // var_dump($promo_for_this_product_array);
                $cartdetail->promo_for_this_product = $promo_for_this_product_array;

                $coupon_filter = array();
                foreach ($used_product_coupons as $used_product_coupon) {
                    // dd($used_product_coupon->cartdetail);
                    if ($used_product_coupon->cartdetail->cart_detail_id == $cartdetail->cart_detail_id) {
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
                            $discount = $original_price - $used_product_coupon->issuedcoupon->discount_value + 0;
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
            })->active()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function($q) use ($retailer)
            {
                $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
            })
            ->whereHas('issuedcoupons',function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
            })->with(array('issuedcoupons' => function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
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
                        inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id AND p.promotion_type = "product" and p.is_coupon = "Y" and p.status = "active"
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
                    $promo_for_this_product = clone $promo_filter;
                    if($promo_filter->rule_type == 'product_discount_by_percentage') {
                        $discount = ($promo_filter->discount_value * $original_price) * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value * 100;
                    } elseif($promo_filter->rule_type == 'product_discount_by_value') {
                        $discount = $promo_filter->discount_value * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value;
                    } elseif ($promo_filter->rule_type == 'new_product_price') {
                        $discount = ($original_price - $promo_filter->discount_value) * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        // dd($discount);
                        $promo_for_this_product->discount_str = $promo_filter->discount_value;
                    }
                    $promo_for_this_product->promotion_id = $promo_filter->promotion_id;
                    $promo_for_this_product->promotion_name = $promo_filter->promotion_name;
                    $promo_for_this_product->rule_type = $promo_filter->rule_type;
                    $promo_for_this_product->discount = $discount;
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

                    $promo_vat = ($promo_with_tax - $discount);
                    // $promo_vat = ($discount * $cartdetail->quantity);
                    
                    $vat = $vat - $promo_vat;
                    $promo_with_tax = $promo_with_tax;
                    $subtotal = $subtotal - $promo_with_tax;
                    $subtotal_wo_tax = $subtotal_wo_tax - ($discount);
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
                            $discount = $original_price - $used_product_coupon->issuedcoupon->discount_value + 0;
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
            })->active()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas('issueretailers', function($q) use ($retailer)
            {
                $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
            })
            ->whereHas('issuedcoupons',function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
            })->with(array('issuedcoupons' => function($q) use($user)
            {
                $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
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

        return $cartdata;
    }
}