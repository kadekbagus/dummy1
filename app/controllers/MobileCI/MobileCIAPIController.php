<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Activity;
use Carbon\Carbon as Carbon;
use Cart;
use CartCoupon;
use CartDetail;
use Category;
use Config;
use Coupon;
use DB;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use Exception;
use IssuedCoupon;
use Lang;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use Product;
use Promotion;
use Retailer;
use stdclass;
use User;
use Validator;
use View;
use EventModel;

class MobileCIAPIController extends ControllerAPI
{
    protected $session = null;

    /**
     * GET - Product list catalogue (this function is used when getting catalogue page with opened families)
     *
     * @param array                                $families     (optional)
     * @param integer                              $family_level (optional)
     * @param integer                              $family_id    (optional)
     * @param string                               $sort_by      (optional)
     * @param string    `sort_mode`     (optional)
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    protected function getProductListCatalogue($families, $family_level, $family_id, $sort_by)
    {
        $user = null;
        try {
            $user = $this->getLoggedInUser();

            $this->registerCustomValidation();

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

            if ($nextfamily < 6) {
                $subfamilies = Category::where('merchant_id', $retailer->parent_id)->whereHas(
                    'product' . $nextfamily,
                    function ($q) use ($family_id, $family_level, $families, $retailer) {
                        $nextfamily = $family_level + 1;
                        for ($i = 1; $i <= count($families); $i++) {
                            $q->where('products.category_id' . $i, $families[$i-1]);
                            $q->whereHas(
                                'retailers',
                                function ($q2) use ($retailer) {
                                    $q2->where('product_retailer.retailer_id', $retailer->merchant_id);
                                }
                            );
                        }

                        $q->where('products.category_id' . $family_level, $family_id)
                            ->where(
                                function ($query) use ($nextfamily) {
                                    $query->whereNotNull('products.category_id' . $nextfamily)->orWhere('products.category_id' . $nextfamily, '<>', 0);
                                }
                            )
                        ->where('products.status', 'active');
                    }
                )->get();
            } else {
                $subfamilies = null;
            }

            $products = Product::with('variants')->whereHas(
                'retailers',
                function ($query) use ($retailer) {
                    $query->where('retailer_id', $retailer->merchant_id);
                }
            )->where('merchant_id', $retailer->parent_id)->active()->where(
                function ($q) use ($family_level, $family_id, $families) {
                    for ($i = 1; $i < count($families); $i++) {
                        $q->where('category_id' . $i, $families[$i-1]);
                    }
                        $q->where('category_id' . $family_level, $family_id);
                    for ($i = $family_level + 1; $i <= 5; $i++) {
                        $q->where(
                            function ($q2) use ($i) {
                                $q2->whereNull('category_id' . $i)->orWhere('category_id' . $i, 0);
                            }
                        );
                    }
                }
            );

            $_products = clone $products;

            // Default sort by
            $sortBy = 'products.product_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get(
                'sort_by',
                function ($_sortBy) use (&$sortBy) {
                    // Map the sortby request to the real column name
                    $sortByMapping = array(
                    'product_name'      => 'products.product_name',
                    'price'             => 'products.price',
                    );

                    $sortBy = $sortByMapping[$_sortBy];
                }
            );

            OrbitInput::get(
                'sort_mode',
                function ($_sortMode) use (&$sortMode) {
                    if (strtolower($_sortMode) !== 'desc') {
                        $sortMode = 'asc';
                    } else {
                        $sortMode = 'desc';
                    }
                }
            );
            $products->orderBy($sortBy, $sortMode);

            $totalRec = $_products->count();
            $listOfRec = $products->get();

            $promotions = DB::select(
                DB::raw(
                    'SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N"
                left join ' . DB::getTablePrefix() . 'promotion_product propro on (pr.promotion_id = propro.promotion_rule_id AND object_type = "discount")
                left join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                left join ' . DB::getTablePrefix() . 'products prod on
                    (
                        (pr.discount_object_type="product" AND propro.product_id = prod.product_id)
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

                WHERE prr.retailer_id = :retailerid OR (p.is_all_retailer = "Y" AND p.merchant_id = :merchantid)
                GROUP BY p.promotion_id
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );

            $couponstocatchs = DB::select(
                DB::raw(
                    'SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                left join ' . DB::getTablePrefix() . 'promotion_product propro on (pr.promotion_id = propro.promotion_rule_id AND object_type = "rule")
                left join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id
                left join ' . DB::getTablePrefix() . 'products prod on
                (
                    (pr.rule_object_type="product" AND propro.product_id = prod.product_id)
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
                WHERE prr.retailer_id = :retailerid OR (p.is_all_retailer = "Y" AND p.merchant_id = :merchantid)
                GROUP BY p.promotion_id
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );
            
            $coupons = DB::select(
                DB::raw(
                    'SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y"))
                left join ' . DB::getTablePrefix() . 'promotion_product propro on (pr.promotion_id = propro.promotion_rule_id AND object_type = "discount")
                left join ' . DB::getTablePrefix() . 'promotion_retailer_redeem prr on prr.promotion_id = p.promotion_id
                left join ' . DB::getTablePrefix() . 'products prod on
                (
                    (pr.discount_object_type="product" AND propro.product_id = prod.product_id)
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
                WHERE ic.expired_date >= "' . Carbon::now() . '" AND ic.user_id = :userid AND ic.expired_date >= "' . Carbon::now() . '" AND (prr.retailer_id = :retailerid OR (p.is_all_retailer_redeem = "Y" AND p.merchant_id = :merchantid))
                GROUP BY p.promotion_id'
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id)
            );
            
            $product_on_promo = array();
            foreach ($promotions as $promotion) {
                $product_on_promo[] = $promotion->product_id;
            }

            foreach ($listOfRec as $product) {
                $prices = array();
                foreach ($product->variants as $variant) {
                    $prices[] = $variant->price;
                }

                // set minimum price
                $min_price = min($prices);
                $product->min_price = $min_price + 0;

                // set on_promo flag
                $promo_for_this_product = array_filter(
                    $promotions,
                    function ($v) use ($product) {
                        if($v->is_all_product_discount == 'N') {
                            return $v->product_id == $product->product_id;
                        } else {
                            return $v;
                        }
                    }
                );
                if (count($promo_for_this_product) > 0) {
                    $discounts=0;
                    $temp_price = $min_price;
                    $arr_promo = array();
                    foreach ($promo_for_this_product as $promotion) {
                        if (! in_array($promotion->promotion_id, $arr_promo)) {
                            if ($promotion->rule_type == 'product_discount_by_percentage' || $promotion->rule_type == 'cart_discount_by_percentage') {
                                $discount = min($prices) * $promotion->discount_value;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $discounts = $discounts + $discount;
                            } elseif ($promotion->rule_type == 'product_discount_by_value' || $promotion->rule_type == 'cart_discount_by_value') {
                                $discount = $promotion->discount_value;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $discounts = $discounts + $discount;
                            } elseif ($promotion->rule_type == 'new_product_price') {
                                $new_price = $min_price - $promotion->discount_value;
                                $discount = $new_price;
                                if ($temp_price < $discount) {
                                    $discount = $temp_price;
                                }
                                $discounts = $discounts + $discount;
                            }
                            $arr_promo[] = $promotion->promotion_id;
                            $temp_price = $temp_price - $discount;
                        }
                    }
                    $product->priceafterpromo = $min_price - $discounts;
                    $product->on_promo = true;
                } else {
                    $product->on_promo = false;
                }

                // set coupons to catch flag
                $couponstocatch_this_product = array_filter(
                    $couponstocatchs,
                    function ($v) use ($product) {
                        if ($v->maximum_issued_coupon != 0) {
                            $issued = IssuedCoupon::where('promotion_id', $v->promotion_id)->count();
                            if($v->is_all_product_rule == 'N') {
                                return $v->product_id == $product->product_id && $v->maximum_issued_coupon > $issued;
                            } else {
                                return $v;
                            }
                        } else {
                            if($v->is_all_product_rule == 'N') {
                                return $v->product_id == $product->product_id;
                            } else {
                                return $v;
                            }
                        }
                    }
                );
                $product->on_couponstocatch = false;
                foreach ($couponstocatch_this_product as $couponstocatchsflag) {
                    if ($couponstocatchsflag->coupon_notification == 'Y') {
                        $product->on_couponstocatch |= true;
                    } else {
                        $product->on_couponstocatch |= false;
                    }
                }

                // set coupons flag
                $coupon_for_this_product = array_filter(
                    $coupons,
                    function ($v) use ($product) {
                        if($v->is_all_product_discount == 'N') {
                            return $v->product_id == $product->product_id;
                        } else {
                            return $v;
                        }
                    }
                );
                if (count($coupon_for_this_product) > 0) {
                    $product->on_coupons = true;
                } else {
                    $product->on_coupons = false;
                }

                // set is_new flag
                if ($product->new_from <= \Carbon\Carbon::now() && $product->new_until >= \Carbon\Carbon::now()) {
                    $product->is_new = true;
                } else {
                    $product->is_new = false;
                }
            }

            $data = new stdclass();
            $data->records = $listOfRec;
            $data->subfamilies = $subfamilies;
            $data->promotions = $promotions;
            $data->promo_products = $product_on_promo;
            $data->couponstocatchs = $couponstocatchs;

            return $data;

        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }

    }

    /**
     * Custom validations block
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return void
     */
    protected function registerCustomValidation()
    {
        // Check user email address, it should not exists
        Validator::extend(
            'orbit.email.exists',
            function ($attribute, $value, $parameters) {
                $user = User::active()
                        ->where('user_email', $value)
                        ->first();

                if (! empty($user)) {
                    return false;
                }

                \App::instance('orbit.validation.user', $user);

                return true;
            }
        );

        // Check category, it should exists
        Validator::extend(
            'orbit.exists.category',
            function ($attribute, $value, $parameters) {
                $category = Category::active()
                        ->where('category_id', $value)
                        ->first();

                if (empty($category)) {
                    return false;
                }

                \App::instance('orbit.validation.category', $category);

                return true;
            }
        );

        // Check product, it should exists
        Validator::extend(
            'orbit.exists.product',
            function ($attribute, $value, $parameters) {
                $product = Product::active()
                        ->where('product_id', $value)
                        ->first();

                if (empty($product)) {
                    return false;
                }

                \App::instance('orbit.validation.product', $product);

                return true;
            }
        );

        // Check promotion, it should exists
        Validator::extend(
            'orbit.exists.promotion',
            function ($attribute, $value, $parameters) {
                $retailer = $this->getRetailerInfo();

                $promotion = Promotion::with(
                    array('retailers' => function ($q) use ($retailer) {
                        $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                    })
                )->active()
                ->where('promotion_id', $value)
                ->first();

                if (empty($promotion)) {
                    return false;
                }

                \App::instance('orbit.validation.promotion', $promotion);

                return true;
            }
        );

        // Check coupon, it should exists
        Validator::extend(
            'orbit.exists.coupon',
            function ($attribute, $value, $parameters) {
                $retailer = $this->getRetailerInfo();

                $coupon = Coupon::with(
                    array('issueretailers' => function ($q) use ($retailer) {
                        $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                    })
                )->active()
                ->where('promotion_id', $value)
                ->first();

                if (empty($coupon)) {
                    return false;
                }

                \App::instance('orbit.validation.coupon', $coupon);

                return true;
            }
        );

        // Check product variant, it should exists
        Validator::extend(
            'orbit.exists.productvariant',
            function ($attribute, $value, $parameters) {
                $product = \ProductVariant::active()
                        ->where('product_variant_id', $value)
                        ->first();

                if (empty($product)) {
                    return false;
                }

                \App::instance('orbit.validation.productvariant', $product);

                return true;
            }
        );

        // Check coupons, it should exists
        Validator::extend(
            'orbit.exists.issuedcoupons',
            function ($attribute, $value, $parameters) {
                $retailer = $this->getRetailerInfo();

                $user = $this->getLoggedInUser();

                $coupon = Coupon::whereHas(
                    'issuedcoupons',
                    function ($q) use ($user, $value) {
                        $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.issued_coupon_id', $value)->where('expired_date', '>=', Carbon::now());
                    }
                )
                ->whereHas(
                    'redeemretailers',
                    function ($q) use ($retailer) {
                        $q->where('promotion_retailer_redeem.retailer_id', $retailer->merchant_id);
                    }
                )
                ->active()
                ->first();

                if (empty($coupon)) {
                    return false;
                }

                \App::instance('orbit.validation.issuedcoupons', $coupon);

                return true;
            }
        );

        // Check event, it should exists
        Validator::extend(
            'orbit.exists.event',
            function ($attribute, $value, $parameters) {
                $retailer = $this->getRetailerInfo();

                $event = EventModel::active()
                ->where('event_id', $value)
                ->where(function($q) use ($retailer) {
                    $q->where(function($q2) use($retailer) {
                        $q2->where('is_all_retailer', 'Y');
                        $q2->where('merchant_id', $retailer->parent->merchant_id);
                    });
                    $q->orWhere(function($q2) use ($retailer) {
                        $q2->where('is_all_retailer', 'N');
                        $q2->whereHas('retailers', function($q3) use($retailer) {
                            $q3->where('event_retailer.retailer_id', $retailer->merchant_id);
                        });
                    });
                })
                ->where(
                    function ($q) {
                        $q->where(
                            function ($q2) {
                                $q2->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now());
                            }
                        );
                        $q->orWhere(
                            function ($q2) {
                                $q2->where('begin_date', '<=', Carbon::now())->where('is_permanent', 'Y');
                            }
                        );
                    }
                )
                ->first();

                if (empty($event)) {
                    return false;
                }

                \App::instance('orbit.validation.event', $event);

                return true;
            }
        );

        // Check cart, it should exists
        Validator::extend(
            'orbit.exists.cartdetailid',
            function ($attribute, $value, $parameters) {
                $retailer = $this->getRetailerInfo();

                $user = $this->getLoggedInUser();

                $cartdetail = CartDetail::whereHas(
                    'cart',
                    function ($q) use ($user, $retailer) {
                        $q->where('carts.customer_id', $user->user_id)->where('carts.retailer_id', $retailer->merchant_id);
                    }
                )->active()
                        ->where('cart_detail_id', $value)
                        ->first();

                if (empty($cartdetail)) {
                    return false;
                }

                \App::instance('orbit.validation.cartdetailid', $cartdetail);

                return true;
            }
        );

        // Check cart, it should exists
        Validator::extend(
            'orbit.exists.cartid',
            function ($attribute, $value, $parameters) {
                $retailer = $this->getRetailerInfo();

                $user = $this->getLoggedInUser();

                $cart = Cart::where('cart_id', $value)->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->active()->first();

                if (empty($cart)) {
                    return false;
                }

                \App::instance('orbit.validation.cartid', $cart);

                return true;
            }
        );
    }

    /**
     * Redirect user if not logged in to sign page
     *
     * @param object $e - Error object
     *
     * @return \Illuminate\Support\Facades\Redirect
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    protected function redirectIfNotLoggedIn($e)
    {
        if (Config::get("app.debug")) {
            return \Response::make($e);
        }
        if ($e->getMessage() === 'Session error: user not found.' || $e->getMessage() === 'Invalid session data.' || $e->getMessage() === 'IP address miss match.' || $e->getMessage() === 'Session has ben expires.' || $e->getMessage() === 'User agent miss match.') {
            return \Redirect::to('/customer');
        } else {
            return \Redirect::to('/customer/logout');
        }
    }

    /**
     * GET - Get current active retailer
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getRetailerInfo()
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

        if ($this->session->read('logged_in') !== true || ! $userId) {
            throw new Exception('Invalid session data.');
        }

        $user = User::with('userDetail')->find($userId);

        if (! $user) {
            throw new Exception('Session error: user not found.');
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

    /**
     * Get cart total item for toolbar
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return Cart $cart->total_item
     */
    protected function getCartForToolbar()
    {
        $user = $this->getLoggedInUser();
        $retailer = $this->getRetailerInfo();
        if ($retailer->parent->enable_shopping_cart == 'yes') {
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
            return $cart->total_item;
        } else {
            return 0;
        }


    }

    /**
     * Get current user active cart
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return Object $cartdata
     */
    protected function getCartData()
    {
        try {
            $user = $this->getLoggedInUser();
            $retailer = $this->getRetailerInfo();
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
            $promo_products = DB::select(
                DB::raw(
                    'SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N" AND p.merchant_id = :merchantid
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
                )'
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );

            $cartdetails = CartDetail::with(
                array('product' => function ($q) {
                    $q->where('products.status', 'active');
                }, 'variant' => function ($q) {
                    $q->where('product_variants.status', 'active');
                })
            )
            ->whereHas(
                'product',
                function ($q) {
                    $q->where('products.status', 'active');
                }
            )
            ->where('status', 'active')->where('cart_id', $cart->cart_id)->get();
            $cartdata = new stdclass();
            $cartdata->cart = $cart;
            $cartdata->cartdetails = $cartdetails;

            return $cartdata;
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * Calculate current active user cart items, including taxes calculation
     *
     * @param object $user     (required) - The current User object
     * @param object $retailer (required) - The current Retailer object
     *
     * @return Object $cartdata
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    protected function cartCalc($user, $retailer)
    {
        // get the cart
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

        // get the cart details
        $cartdetails = CartDetail::with(
            array('product' => function ($q) {
                $q->where('products.status', 'active');
            }, 'variant' => function ($q) {
                $q->where('product_variants.status', 'active');
            }),
            'tax1',
            'tax2'
        )
            ->active()
            ->where('cart_id', $cart->cart_id)
            ->whereHas(
                'product',
                function ($q) {
                    $q->where('products.status', 'active');
                }
            )
            ->get();

        // create new object to contain everything
        $cartdata = new stdclass();
        $cartdata->cart = $cart;
        $cartdata->cartdetails = $cartdetails;

        // get the product based promos
        $promo_products = DB::select(
            DB::raw(
                'SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
            inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N" AND p.merchant_id = :merchantid
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
            )'
            ),
            array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
        );

        // get the used product coupons
        $used_product_coupons = CartCoupon::with(
            array('cartdetail' => function ($q) {
                $q->join('product_variants', 'cart_details.product_variant_id', '=', 'product_variants.product_variant_id');
            }, 'issuedcoupon' => function ($q) use ($user) {
                $q->where('issued_coupons.user_id', $user->user_id)
                    ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                    ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');
            })
        )->whereHas(
            'issuedcoupon',
            function ($q) use ($user) {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.status', 'deleted');
            }
        )->whereHas(
            'cartdetail',
            function ($q) {
                    $q->where('cart_coupons.object_type', '=', 'cart_detail');
            }
        )->get();

        // get the cart based promos
        $promo_carts = Promotion::with('promotionrule')->active()->where('is_coupon', 'N')->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas(
            'retailers',
            function ($q) use ($retailer) {
                $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
            }
        )
        ->where(
            function ($q) {
                $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(
                    function ($qr) {
                        $qr->where('begin_date', '<=', Carbon::now())->where('is_permanent', '=', 'Y');
                    }
                );
            }
        )->get();

        // get the used cart based coupons
        $used_cart_coupons = CartCoupon::with(
            array('cart', 'issuedcoupon' => function ($q) use ($user) {
                $q->where('issued_coupons.user_id', $user->user_id)
                    ->where('issued_coupons.status', 'deleted')
                    ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                    ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');
            })
        )
        ->whereHas(
            'cart',
            function ($q) use ($cartdata) {
                $q->where('cart_coupons.object_type', '=', 'cart')
                    ->where('cart_coupons.object_id', '=', $cartdata->cart->cart_id);
            }
        )
        ->where('cart_coupons.object_type', '=', 'cart')->get();

        $subtotal = 0;
        $subtotal_wo_tax = 0;
        $vat = 0;
        $total = 0;

        $taxes = \MerchantTax::active()->where('merchant_id', $retailer->parent_id)->get();

        $vat_included = $retailer->parent->vat_included;

        if ($vat_included === 'yes') {
            // tax included part
            foreach ($cartdata->cartdetails as $cartdetail) {
                $attributes = array();
                $product_vat_value = 0;
                $original_price = $cartdetail->variant->price;
                $original_ammount = $original_price * $cartdetail->quantity;
                $ammount_after_promo = $original_ammount;
                $product_price_wo_tax = $original_price;

                // collect available product based coupon for the item
                $available_product_coupons = DB::select(
                    DB::raw(
                        'SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                        inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y"))
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

                        '
                    ),
                    array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $cartdetail->product_id)
                );

                $cartdetail->available_product_coupons = count($available_product_coupons);

                // calculate tax 1 - government
                if (! is_null($cartdetail->tax1)) {
                    $tax1 = $cartdetail->tax1->tax_value;
                    if (! is_null($cartdetail->tax2)) {
                        $tax2 = $cartdetail->tax2->tax_value;
                        if ($cartdetail->tax2->tax_type == 'service') {
                            $pwot  = $original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                            $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                            $tax1_total_value = $tax1_value * $cartdetail->quantity;
                        } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                            $tax1_value = ($original_price / (1 + $tax1 + $tax2)) * $tax1;
                            $tax1_total_value = $tax1_value * $cartdetail->quantity;
                        }
                    } else {
                        $tax1_value = ($original_price / (1 + $tax1)) * $tax1;
                        $tax1_total_value = $tax1_value * $cartdetail->quantity;
                    }
                    foreach ($taxes as $tax) {
                        if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                            $tax->total_tax = $tax->total_tax + $tax1_total_value;
                            $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax1_total_value;
                        }
                    }
                } else {
                    $tax1 = 0;
                }

                // calculate tax 2 - non government
                if (! is_null($cartdetail->tax2)) {
                    $tax2 = $cartdetail->tax2->tax_value;
                    // TODO: check if we set default value is zero are the tax
                    // calculcations still valid
                    $tax2_total_value = 0;
                    if (! is_null($cartdetail->tax1)) {
                        if ($cartdetail->tax2->tax_type == 'service') {
                            $tax2_value = ($original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                            $tax2_total_value = $tax2_value * $cartdetail->quantity;
                        } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                            $tax2_value = ($original_price / (1 + $tax1 + $tax2)) * $tax2;
                            $tax2_total_value = $tax2_value * $cartdetail->quantity;
                        }
                    }
                    foreach ($taxes as $tax) {
                        if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                            $tax->total_tax = $tax->total_tax + $tax2_total_value;
                            $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax2_total_value;
                        }
                    }
                } else {
                    $tax2 = 0;
                }

                // get item price without tax
                if (! is_null($cartdetail->tax2)) {
                    if ($cartdetail->tax2->tax_type == 'service') {
                        $product_price_wo_tax = $original_price / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                    } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                        $product_price_wo_tax = $original_price / (1 + $tax1 + $tax2);
                    }
                } else {
                    $product_price_wo_tax = $original_price / (1 + $tax1);
                }

                $product_vat = ($original_price - $product_price_wo_tax) * $cartdetail->quantity;
                $vat = $vat + $product_vat;
                $product_price_wo_tax = $product_price_wo_tax * $cartdetail->quantity;
                $subtotal = $subtotal + $original_ammount;
                $subtotal_wo_tax = $subtotal_wo_tax + $product_price_wo_tax;

                $temp_price = $original_ammount;
                $promo_for_this_product_array = array();
                $promo_filters = array_filter(
                    $promo_products,
                    function ($v) use ($cartdetail) {
                        return $v->product_id == $cartdetail->product_id;
                    }
                );

                // product based promo calculation
                foreach ($promo_filters as $promo_filter) {
                    $promo_for_this_product = new stdclass();
                    $promo_for_this_product = clone $promo_filter;
                    if ($promo_filter->rule_type == 'product_discount_by_percentage' || $promo_filter->rule_type == 'cart_discount_by_percentage') {
                        $discount = $promo_filter->discount_value * $original_price * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value * 100;
                    } elseif ($promo_filter->rule_type == 'product_discount_by_value' || $promo_filter->rule_type == 'cart_discount_by_value') {
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

                    if (! is_null($cartdetail->tax1)) {
                        $tax1 = $cartdetail->tax1->tax_value;
                        if (! is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $pwot  = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                                $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                                $tax1_total_value = $tax1_value;
                            } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                $tax1_value = ($discount / (1 + $tax1 + $tax2)) * $tax1;
                                $tax1_total_value = $tax1_value;
                            }
                        } else {
                            $tax1_value = ($discount / (1 + $tax1)) * $tax1;
                            $tax1_total_value = $tax1_value;
                        }

                        foreach ($taxes as $tax) {
                            if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                            }
                        }
                    }

                    if (! is_null($cartdetail->tax2)) {
                        $tax2 = $cartdetail->tax2->tax_value;
                        $tax2_total_value = 0;
                        if (! is_null($cartdetail->tax1)) {
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $tax2_value = ($discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                                $tax2_total_value = $tax2_value;
                            } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                $tax2_value = ($discount / (1 + $tax1 + $tax2)) * $tax2;
                                $tax2_total_value = $tax2_value;
                            }
                        }
                        foreach ($taxes as $tax) {
                            if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                            }
                        }
                    }

                    if (! is_null($cartdetail->tax2)) {
                        if ($cartdetail->tax2->tax_type == 'service') {
                            $promo_wo_tax = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                        } elseif ($cartdetail->tax2->tax_type == 'luxury') {
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

                $cartdetail->promo_for_this_product = $promo_for_this_product_array;

                // product based coupon calculation
                $coupon_filter = array();
                foreach ($used_product_coupons as $used_product_coupon) {
                    if ($used_product_coupon->cartdetail->cart_detail_id == $cartdetail->cart_detail_id) {
                        if ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_percentage' || $used_product_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                            $discount = $used_product_coupon->issuedcoupon->discount_value * $original_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value * 100;
                        } elseif ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_value' || $used_product_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
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

                        if (! is_null($cartdetail->tax1)) {
                            $tax1 = $cartdetail->tax1->tax_value;
                            if (! is_null($cartdetail->tax2)) {
                                $tax2 = $cartdetail->tax2->tax_value;
                                if ($cartdetail->tax2->tax_type == 'service') {
                                    $pwot  = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                                    $tax1_value = ($pwot + ($pwot * $tax2)) * $tax1;
                                    $tax1_total_value = $tax1_value;
                                } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                    $tax1_value = ($discount / (1 + $tax1 + $tax2)) * $tax1;
                                    $tax1_total_value = $tax1_value;
                                }
                            } else {
                                $tax1_value = ($discount / (1 + $tax1)) * $tax1;
                                $tax1_total_value = $tax1_value;
                            }
                            foreach ($taxes as $tax) {
                                if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                }
                            }
                        }

                        if (! is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            $tax2_total_value = 0;
                            if (! is_null($cartdetail->tax1)) {
                                if ($cartdetail->tax2->tax_type == 'service') {
                                    $tax2_value = ($discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2))) * $tax2;
                                    $tax2_total_value = $tax2_value;
                                } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                    $tax2_value = ($discount / (1 + $tax1 + $tax2)) * $tax2;
                                    $tax2_total_value = $tax2_value;
                                }
                            }
                            foreach ($taxes as $tax) {
                                if ($tax->merchant_tax_id == $cartdetail->tax2->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax2_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax2_total_value;
                                }
                            }
                        }

                        if (! is_null($cartdetail->tax2)) {
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $coupon_wo_tax = $discount / (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                            } elseif ($cartdetail->tax2->tax_type == 'luxury') {
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

                $cartdetail->coupon_for_this_product = $coupon_filter;
                $cartdetail->original_price = $original_price;
                $cartdetail->original_ammount = $original_ammount;
                $cartdetail->ammount_after_promo = $ammount_after_promo;

                // contain item attribute
                if ($cartdetail->attributeValue1['value']) {
                    $attributes[] = $cartdetail->attributeValue1['value'];
                }
                if ($cartdetail->attributeValue2['value']) {
                    $attributes[] = $cartdetail->attributeValue2['value'];
                }
                if ($cartdetail->attributeValue3['value']) {
                    $attributes[] = $cartdetail->attributeValue3['value'];
                }
                if ($cartdetail->attributeValue4['value']) {
                    $attributes[] = $cartdetail->attributeValue4['value'];
                }
                if ($cartdetail->attributeValue5['value']) {
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
            $discount_cart_promo_wo_tax = 0;
            $discount_cart_coupon = 0;
            $cart_promo_taxes = 0;
            $subtotal_before_cart_promo = $subtotal;
            $temp_subtotal = $subtotal;

            // cart based promo calculation
            if (! empty($promo_carts)) {
                foreach ($promo_carts as $promo_cart) {
                    if ($subtotal >= $promo_cart->promotionrule->rule_value) {
                        if ($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') {
                            $discount = $subtotal * $promo_cart->promotionrule->discount_value;
                            if ($temp_subtotal < $discount) {
                                $discount = $temp_subtotal;
                            }
                            $promo_cart->disc_val_str = '-' . ($promo_cart->promotionrule->discount_value * 100) . '%';
                            $promo_cart->disc_val = '-' . ($subtotal * $promo_cart->promotionrule->discount_value);
                        } elseif ($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') {
                            $discount = $promo_cart->promotionrule->discount_value;
                            if ($temp_subtotal < $discount) {
                                $discount = $temp_subtotal;
                            }
                            $promo_cart->disc_val_str = '-' . $promo_cart->promotionrule->discount_value + 0;
                            $promo_cart->disc_val = '-' . $promo_cart->promotionrule->discount_value + 0;
                        }

                        $activityPage = Activity::mobileci()
                                    ->setActivityType('add');
                        $activityPageNotes = sprintf('Add Promotion: %s', $promo_cart->promotion_id);
                        $activityPage->setUser($user)
                            ->setActivityName('add_promotion')
                            ->setActivityNameLong('Add Promotion ' . $promo_cart->promotion_name)
                            ->setObject($promo_cart)
                            ->setPromotion($promo_cart)
                            ->setModuleName('Promotion')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

                        $temp_subtotal = $temp_subtotal - $discount;
                        $cart_promo_wo_tax = $discount / (1 + $cart_vat);
                        $cart_promo_tax = $discount - $cart_promo_wo_tax;
                        $cart_promo_taxes = $cart_promo_taxes + $cart_promo_tax;

                        foreach ($taxes as $tax) {
                            if (! empty($tax->total_tax)) {
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

            $coupon_carts = Coupon::join(
                'promotion_rules',
                function ($q) use ($subtotal) {
                    $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.discount_object_type', '=', 'cash_rebate')->where('promotion_rules.coupon_redeem_rule_value', '<=', $subtotal);
                }
            )->active()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas(
                'issueretailers',
                function ($q) use ($retailer) {
                        $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                }
            )
            ->whereHas(
                'issuedcoupons',
                function ($q) use ($user) {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
                }
            )->with(
                array('issuedcoupons' => function ($q) use ($user) {
                        $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
                })
            )
            ->get();

            // product based coupon calculation
            $available_coupon_carts = array();
            $cart_discount_by_percentage_counter = 0;
            $discount_cart_coupon = 0;
            $discount_cart_coupon_wo_tax = 0;
            $total_cart_coupon_discount = 0;
            $cart_coupon_taxes = 0;
            $acquired_coupon_carts = array();
            if (! empty($used_cart_coupons)) {
                foreach ($used_cart_coupons as $used_cart_coupon) {
                    if (! empty($used_cart_coupon->issuedcoupon->coupon_redeem_rule_value)) {
                        if ($subtotal >= $used_cart_coupon->issuedcoupon->coupon_redeem_rule_value) {
                            if ($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                                $used_cart_coupon->disc_val_str = '-' . ($used_cart_coupon->issuedcoupon->discount_value * 100).'%';
                                $used_cart_coupon->disc_val = '-' . ($used_cart_coupon->issuedcoupon->discount_value * $subtotal);
                                $discount = $subtotal * $used_cart_coupon->issuedcoupon->discount_value;
                                if ($temp_subtotal < $discount) {
                                    $discount = $temp_subtotal;
                                }
                                $cart_discount_by_percentage_counter++;
                            } elseif ($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
                                $used_cart_coupon->disc_val_str = '-' . $used_cart_coupon->issuedcoupon->discount_value + 0;
                                $used_cart_coupon->disc_val = '-' . $used_cart_coupon->issuedcoupon->discount_value + 0;
                                $discount = $used_cart_coupon->issuedcoupon->discount_value;
                                if ($temp_subtotal < $discount) {
                                    $discount = $temp_subtotal;
                                }
                            }
                            $temp_subtotal = $temp_subtotal - $discount;
                            $cart_coupon_wo_tax = $discount / (1 + $cart_vat);
                            $cart_coupon_tax = $discount - $cart_coupon_wo_tax;

                            foreach ($taxes as $tax) {
                                if (! empty($tax->total_tax)) {
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
                            $used_cart_coupon->delete(true);
                            $this->commit();
                        }
                    }
                }
            }

            // contain user available cart based coupon
            if (! empty($coupon_carts)) {
                foreach ($coupon_carts as $coupon_cart) {
                    if ($subtotal >= $coupon_cart->coupon_redeem_rule_value) {
                        if ($coupon_cart->rule_type == 'cart_discount_by_percentage') {
                            if ($cart_discount_by_percentage_counter == 0) { // prevent more than one cart_discount_by_percentage
                                $discount = $subtotal * $coupon_cart->discount_value;
                                $cartdiscounts = $cartdiscounts + $discount;
                                $coupon_cart->disc_val_str = '-' . ($coupon_cart->discount_value * 100).'%';
                                $coupon_cart->disc_val = '-' . ($subtotal * $coupon_cart->discount_value);
                                $available_coupon_carts[] = $coupon_cart;
                                // $cart_discount_by_percentage_counter++;
                            }
                        } elseif ($coupon_cart->rule_type == 'cart_discount_by_value') {
                            $discount = $coupon_cart->discount_value;
                            $cartdiscounts = $cartdiscounts + $discount;
                            $coupon_cart->disc_val_str = '-' . $coupon_cart->discount_value + 0;
                            $coupon_cart->disc_val = '-' . $coupon_cart->discount_value + 0;
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

        } else {
            // tax excluded part (the same annotation as above)
            foreach ($cartdata->cartdetails as $cartdetail) {
                $attributes = array();
                $product_vat_value = 0;
                $original_price = $cartdetail->variant->price;
                $subtotal_wo_tax = $subtotal_wo_tax + ($original_price * $cartdetail->quantity);
                $original_ammount = $original_price * $cartdetail->quantity;

                $available_product_coupons = DB::select(
                    DB::raw(
                        'SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                        inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y" and p.status = "active"  and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y"))
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

                        '
                    ),
                    array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $cartdetail->product_id)
                );

                $cartdetail->available_product_coupons = count($available_product_coupons);

                if (! is_null($cartdetail->tax1)) {
                    $tax1 = $cartdetail->tax1->tax_value;
                    if (! is_null($cartdetail->tax2)) {
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
                        if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                            $tax->total_tax = $tax->total_tax + $tax1_total_value;
                            $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo + $tax1_total_value;
                        }
                    }
                } else {
                    $tax1 = 0;
                }

                if (! is_null($cartdetail->tax2)) {
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

                if (! is_null($cartdetail->tax2)) {
                    if ($cartdetail->tax2->tax_type == 'service') {
                        $product_price_with_tax = $original_price * (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                    } elseif ($cartdetail->tax2->tax_type == 'luxury') {
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
                $promo_filters = array_filter(
                    $promo_products,
                    function ($v) use ($cartdetail) {
                        return $v->product_id == $cartdetail->product_id;
                    }
                );

                foreach ($promo_filters as $promo_filter) {
                    $promo_for_this_product = new stdclass();
                    $promo_for_this_product = clone $promo_filter;
                    if ($promo_filter->rule_type == 'product_discount_by_percentage' || $promo_filter->rule_type == 'cart_discount_by_percentage') {
                        $discount = ($promo_filter->discount_value * $original_price) * $cartdetail->quantity;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $promo_for_this_product->discount_str = $promo_filter->discount_value * 100;
                    } elseif ($promo_filter->rule_type == 'product_discount_by_value' || $promo_filter->rule_type == 'cart_discount_by_value') {
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

                    $promo_wo_tax = $discount / (1 + $product_vat_value);
                    if (! is_null($cartdetail->tax1)) {
                        $tax1 = $cartdetail->tax1->tax_value;
                        if (! is_null($cartdetail->tax2)) {
                            $tax2 = $cartdetail->tax2->tax_value;
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $pwt = $discount;
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
                        foreach ($taxes as $tax) {
                            if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                            }
                        }
                    }

                    if (! is_null($cartdetail->tax2)) {
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

                    if (! is_null($cartdetail->tax2)) {
                        if ($cartdetail->tax2->tax_type == 'service') {
                            $promo_with_tax = $discount * (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                        } elseif ($cartdetail->tax2->tax_type == 'luxury') {
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
                foreach ($used_product_coupons as $used_product_coupon) {
                    if ($used_product_coupon->cartdetail->product_variant_id == $cartdetail->product_variant_id) {
                        if ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_percentage' || $used_product_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                            $discount = $used_product_coupon->issuedcoupon->discount_value * $original_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $used_product_coupon->discount_str = $used_product_coupon->issuedcoupon->discount_value * 100;
                        } elseif ($used_product_coupon->issuedcoupon->rule_type == 'product_discount_by_value' || $used_product_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
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

                        if (! is_null($cartdetail->tax1)) {
                            $tax1 = $cartdetail->tax1->tax_value;
                            if (! is_null($cartdetail->tax2)) {
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
                            foreach ($taxes as $tax) {
                                if ($tax->merchant_tax_id == $cartdetail->tax1->merchant_tax_id) {
                                    $tax->total_tax = $tax->total_tax - $tax1_total_value;
                                    $tax->total_tax_before_cart_promo = $tax->total_tax_before_cart_promo - $tax1_total_value;
                                }
                            }
                        }

                        if (! is_null($cartdetail->tax2)) {
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

                        if (! is_null($cartdetail->tax2)) {
                            if ($cartdetail->tax2->tax_type == 'service') {
                                $coupon_with_tax = $discount * (1 + $tax1 + $tax2 + ($tax1 * $tax2));
                            } elseif ($cartdetail->tax2->tax_type == 'luxury') {
                                $coupon_with_tax = $discount * (1 + $tax1 + $tax2);
                            }
                        } else {
                            $coupon_with_tax = $discount * (1 + $tax1);
                        }

                        $coupon_vat = ($coupon_with_tax - $discount);
                        $vat = $vat - $coupon_vat;
                        $subtotal = $subtotal - $coupon_with_tax;
                        $subtotal_wo_tax = $subtotal_wo_tax - $discount;
                        $coupon_filter[] = $used_product_coupon;
                    }
                }

                $cartdetail->coupon_for_this_product = $coupon_filter;

                $cartdetail->original_price = $original_price;
                $cartdetail->original_ammount = $original_ammount;
                $cartdetail->ammount_after_promo = $ammount_after_promo;

                if ($cartdetail->attributeValue1['value']) {
                    $attributes[] = $cartdetail->attributeValue1['value'];
                }
                if ($cartdetail->attributeValue2['value']) {
                    $attributes[] = $cartdetail->attributeValue2['value'];
                }
                if ($cartdetail->attributeValue3['value']) {
                    $attributes[] = $cartdetail->attributeValue3['value'];
                }
                if ($cartdetail->attributeValue4['value']) {
                    $attributes[] = $cartdetail->attributeValue4['value'];
                }
                if ($cartdetail->attributeValue5['value']) {
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
            $temp_subtotal = $subtotal_before_cart_promo_without_tax;

            if (! empty($promo_carts)) {
                foreach ($promo_carts as $promo_cart) {
                    if ($subtotal_before_cart_promo_without_tax >= $promo_cart->promotionrule->rule_value) {
                        if ($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') {
                            $discount = $subtotal_before_cart_promo_without_tax * $promo_cart->promotionrule->discount_value;
                            if ($temp_subtotal < $discount) {
                                $discount = $temp_subtotal;
                            }
                            $promo_cart->disc_val_str = '-' . ($promo_cart->promotionrule->discount_value * 100).'%';
                            $promo_cart->disc_val = '-' . ($subtotal_before_cart_promo_without_tax * $promo_cart->promotionrule->discount_value);
                        } elseif ($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') {
                            $discount = $promo_cart->promotionrule->discount_value;
                            if ($temp_subtotal < $discount) {
                                $discount = $temp_subtotal;
                            }
                            $promo_cart->disc_val_str = '-' . $promo_cart->promotionrule->discount_value + 0;
                            $promo_cart->disc_val = '-' . $promo_cart->promotionrule->discount_value + 0;
                        }

                        $activityPage = Activity::mobileci()
                                    ->setActivityType('add');
                        $activityPageNotes = sprintf('Add Promotion: %s', $promo_cart->promotion_id);
                        $activityPage->setUser($user)
                            ->setActivityName('add_promotion')
                            ->setActivityNameLong('Add Promotion ' . $promo_cart->promotion_name)
                            ->setObject($promo_cart)
                            ->setPromotion($promo_cart)
                            ->setModuleName('Promotion')
                            ->setNotes($activityPageNotes)
                            ->responseOK()
                            ->save();

                        $temp_subtotal = $temp_subtotal - $discount;

                        $cart_promo_with_tax = $discount * (1 + $cart_vat);

                        $cart_promo_tax = $discount / $subtotal_wo_tax * $vat_before_cart_promo;
                        $cart_promo_taxes = $cart_promo_taxes + $cart_promo_tax;

                        foreach ($taxes as $tax) {
                            if (! empty($tax->total_tax)) {
                                $tax_reduction = ($discount / $subtotal_wo_tax) * $cart_promo_tax;
                                $tax->total_tax = $tax->total_tax - $tax_reduction;
                            }
                        }

                        $discount_cart_promo = $discount_cart_promo + $discount;
                        $discount_cart_promo_with_tax = $discount_cart_promo_with_tax - $cart_promo_with_tax;
                        $acquired_promo_carts[] = $promo_cart;
                    }
                }

            }

            $coupon_carts = Coupon::join(
                'promotion_rules',
                function ($q) use ($subtotal_before_cart_promo_without_tax) {
                    $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.discount_object_type', '=', 'cash_rebate')->where('promotion_rules.coupon_redeem_rule_value', '<=', $subtotal_before_cart_promo_without_tax);
                }
            )->active()->where('promotion_type', 'cart')->where('merchant_id', $retailer->parent_id)->whereHas(
                'issueretailers',
                function ($q) use ($retailer) {
                        $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                }
            )
            ->whereHas(
                'issuedcoupons',
                function ($q) use ($user) {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
                }
            )->with(
                array('issuedcoupons' => function ($q) use ($user) {
                        $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.expired_date', '>=', Carbon::now())->active();
                })
            )
            ->get();

            $available_coupon_carts = array();
            $cart_discount_by_percentage_counter = 0;
            $discount_cart_coupon = 0;
            $discount_cart_coupon_with_tax = 0;
            $total_cart_coupon_discount = 0;
            $cart_coupon_taxes = 0;
            $acquired_coupon_carts = array();
            if (! empty($used_cart_coupons)) {
                foreach ($used_cart_coupons as $used_cart_coupon) {
                    if (! empty($used_cart_coupon->issuedcoupon->coupon_redeem_rule_value)) {
                        if ($subtotal_before_cart_promo_without_tax >= $used_cart_coupon->issuedcoupon->coupon_redeem_rule_value) {
                            if ($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_percentage') {
                                $used_cart_coupon->disc_val_str = '-' . ($used_cart_coupon->issuedcoupon->discount_value * 100).'%';
                                $used_cart_coupon->disc_val = '-' . ($used_cart_coupon->issuedcoupon->discount_value * $subtotal_before_cart_promo_without_tax);
                                $discount = $subtotal_before_cart_promo_without_tax * $used_cart_coupon->issuedcoupon->discount_value;
                                if ($temp_subtotal < $discount) {
                                    $discount = $temp_subtotal;
                                }
                                $cart_discount_by_percentage_counter++;
                            } elseif ($used_cart_coupon->issuedcoupon->rule_type == 'cart_discount_by_value') {
                                $used_cart_coupon->disc_val_str = '-' . $used_cart_coupon->issuedcoupon->discount_value + 0;
                                $used_cart_coupon->disc_val = '-' . $used_cart_coupon->issuedcoupon->discount_value + 0;
                                $discount = $used_cart_coupon->issuedcoupon->discount_value;
                                if ($temp_subtotal < $discount) {
                                    $discount = $temp_subtotal;
                                }
                            }
                            $temp_subtotal = $temp_subtotal - $discount;
                            $cart_coupon_with_tax = $discount * (1 + $cart_vat);
                            $cart_coupon_tax = $discount / $subtotal_wo_tax * $vat_before_cart_promo;
                            $cart_coupon_taxes = $cart_coupon_taxes + $cart_coupon_tax;

                            foreach ($taxes as $tax) {
                                if (! empty($tax->total_tax)) {
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
                            $used_cart_coupon->delete(true);
                            $this->commit();
                        }
                    }
                }
            }

            if (! empty($coupon_carts)) {
                foreach ($coupon_carts as $coupon_cart) {
                    if ($subtotal_before_cart_promo_without_tax >= $coupon_cart->coupon_redeem_rule_value) {
                        if ($coupon_cart->rule_type == 'cart_discount_by_percentage') {
                            if ($cart_discount_by_percentage_counter == 0) { // prevent more than one cart_discount_by_percentage
                                $discount = $subtotal_before_cart_promo_without_tax * $coupon_cart->discount_value;
                                $cartdiscounts = $cartdiscounts + $discount;
                                $coupon_cart->disc_val_str = '-' . ($coupon_cart->discount_value * 100).'%';
                                $coupon_cart->disc_val = '-' . ($subtotal_before_cart_promo_without_tax * $coupon_cart->discount_value);
                                $available_coupon_carts[] = $coupon_cart;
                                // $cart_discount_by_percentage_counter++;
                            }
                        } elseif ($coupon_cart->rule_type == 'cart_discount_by_value') {
                            $discount = $coupon_cart->discount_value;
                            $cartdiscounts = $cartdiscounts + $discount;
                            $coupon_cart->disc_val_str = '-' . $coupon_cart->discount_value + 0;
                            $coupon_cart->disc_val = '-' . $coupon_cart->discount_value + 0;
                            $available_coupon_carts[] = $coupon_cart;
                        }
                    } else {
                        $coupon_cart->disc_val = $coupon_cart->rule_value;
                    }
                }
            }

            $subtotal_wo_tax = $subtotal_wo_tax - $discount_cart_promo - $discount_cart_coupon;
            $subtotal = $subtotal + $discount_cart_promo_with_tax + $discount_cart_coupon_with_tax;
            $vat = $vat - $cart_promo_taxes - $cart_coupon_taxes;

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

    /**
     * String manipulation blocks
     * @param string $str - string value
     * @return string
     */
    protected function just40CharMid($str)
    {
        $nnn = strlen($str);
        if ($nnn>40) {
            $all = explode('::break-here::', wordwrap($str, 38, '::break-here::'));
            $tmp = '';
            foreach ($all as $str) {
                $space = round((40 - strlen($str)) / 2);
                $spc = '';
                for ($i = 0; $i < $space; $i++) {
                    $spc .= ' ';
                }
                $tmp .= $spc . $str . " \n";
            }
        } else {
            $space = round((40 - strlen($str)) / 2);
            $spc = '';
            for ($i = 0; $i < $space; $i++) {
                $spc .= ' ';
            }
            $tmp = $spc . $str . " \n";
        }

        return $tmp;
    }

    /**
     * String manipulation blocks
     * @param string  $name  - name value
     * @param decimal $price - price value
     * @param integer $qty   - qty value
     * @param string  $sku   - sku value
     * @return string
     */
    protected function productListFormat($name, $price, $qty, $sku)
    {
        $all  = '';
        $sbT = number_format($price * $qty, 2);
        $space = 40 - strlen($name) - strlen($sbT);
        $spc = '';
        for ($i = 0; $i < $space; $i++) {
            $spc .= ' ';
        }
        $all .= $name . $spc . $sbT . " \n";
        $all .= '   ' . $qty . ' x ' . number_format($price, 2) . ' (' . $sku . ')' . " \n";

        return $all;
    }

    /**
     * String manipulation blocks
     * @param string  $discount_name  - discount name value
     * @param decimal $discount_value - discount value
     * @return string
     */
    protected function discountListFormat($discount_name, $discount_value)
    {
        $all  = '';
        $sbT = number_format($discount_value, 2);
        $space = 36 - strlen($discount_name) - strlen($sbT);
        $spc = '';
        for ($i = 0; $i < $space; $i++) {
            $spc .= ' ';
        }
        $all .= '   ' . $discount_name . $spc . "-" . $sbT . " \n";

        return $all;
    }

    /**
     * String manipulation blocks
     * @param string $left  - Left value
     * @param string $right - Right value
     * @return string
     */
    protected function leftAndRight($left, $right)
    {
        $all  = '';
        $space = 40 - strlen($left) - strlen($right);
        $spc = '';
        for ($i = 0; $i < $space; $i++) {
            $spc .= ' ';
        }
        $all .= $left . $spc . $right . " \n";

        return $all;
    }
}