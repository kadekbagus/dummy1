<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Activity;
use Carbon\Carbon as Carbon;
use Config;
use DB;
use Exception;
use IssuedCoupon;
use Lang;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use Product;
use stdclass;
use Validator;
use View;

class CouponController extends MobileCIAPIController
{

    /**
     * GET - Coupon listing page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\View\View
     */
    public function getCouponList()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $promotions = DB::select(
                DB::raw(
                    'SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y" AND p.status = "active" AND ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y"))
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
                WHERE ic.expired_date >= "' . Carbon::now() .'" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND ic.expired_date >= "' . Carbon::now() . '" GROUP BY ic.issued_coupon_code ORDER BY ic.expired_date ASC'
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id)
            );

            if (count($promotions) > 0) {
                $data = new stdclass();
                $data->status = 1;
                $data->records = $promotions;
            } else {
                $data = new stdclass();
                $data->status = 0;
            }

            foreach ($promotions as $promotion) {
                if (empty($promotion->promo_image)) {
                    $promotion->promo_image = 'mobile-ci/images/default_product.png';
                }
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
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Coupon List');
            $activityPage->setUser($user)
                ->setActivityName('view_page_coupon_list')
                ->setActivityNameLong('View (Coupon List) Failed')
                ->setObject(null)
                ->setModuleName('Catalogue')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Coupon detail page
     *
     * @param string    `couponid`       (required) - The coupon ID
     * @param string    `sort_by`        (optional)
     * @param string    `sort_mode`      (optional)
     *
     * @return \Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            $products = Product::whereHas(
                'retailers',
                function ($query) use ($retailer) {
                            $query->where('retailer_id', $retailer->merchant_id);
                }
            )->where('merchant_id', $retailer->parent_id)->active();

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

            $cartitems = $this->getCartForToolbar();

            $all_promotions = DB::select(
                DB::raw(
                    'SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N"
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
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid'
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );

            $promotions = DB::select(
                DB::raw(
                    'SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
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
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid'
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );

            $product_on_promo = array();
            foreach ($promotions as $promotion) {
                if (empty($promotion->promo_image)) {
                    $promotion->promo_image = 'mobile-ci/images/default_product.png';
                }
                $product_on_promo[] = $promotion->product_id;
            }

            $couponstocatchs = DB::select(
                DB::raw(
                    'SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
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
                WHERE p.merchant_id = :merchantid AND prr.retailer_id = :retailerid'
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );

            $coupons = DB::select(
                DB::raw(
                    'SELECT *, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y"))
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
                WHERE ic.issued_coupon_id = :issuedid AND ic.expired_date >= "' . Carbon::now() . '" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid'
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'issuedid' => $promoid)
            );
            $product_on_coupon = array();
            foreach ($coupons as $coupon) {
                if (empty($coupon->promo_image)) {
                    $coupon->promo_image = 'mobile-ci/images/default_product.png';
                }
                $product_on_coupon[] = $coupon->product_id;
            }

            if (! empty($product_on_coupon)) {
                $products->whereIn('products.product_id', $product_on_coupon);
            } else {
                $products->where('product_id', '-1');
            }

            $totalRec = $_products->count();
            $listOfRec = $products->get();

            foreach ($listOfRec as $product) {
                $prices = array();
                foreach ($product->variants as $variant) {
                    $prices[] = $variant->price;
                }

                // set minimum price
                $min_price = min($prices);
                $product->min_price = $min_price + 0;

                // set on_promo flag
                $temp_price = $min_price;
                $promo_for_this_product = array_filter(
                    $all_promotions,
                    function ($v) use ($product) {
                        return $v->product_id == $product->product_id;
                    }
                );
                if (count($promo_for_this_product) > 0) {
                    $discounts=0;
                    foreach ($promo_for_this_product as $promotion) {
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
                        $temp_price = $temp_price - $discount;
                    }
                    $product->on_promo = true;
                    $product->priceafterpromo = $min_price - $discounts;
                } else {
                    $product->on_promo = false;
                }

                // set coupons to catch flag
                $couponstocatch_this_product = array_filter(
                    $couponstocatchs,
                    function ($v) use ($product) {
                        if ($v->maximum_issued_coupon != 0) {
                            $issued = IssuedCoupon::where('promotion_id', $v->promotion_id)->count();

                            return $v->product_id == $product->product_id && $v->maximum_issued_coupon > $issued;
                        } else {
                             return $v->product_id == $product->product_id;
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
                        return $v->product_id == $product->product_id;
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

            // should not be limited (needs to be erased)
            $search_limit = Config::get('orbit.shop.search_limit');
            if ($totalRec>$search_limit) {
                $data = new stdclass();
                $data->status = 0;
            } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = count($listOfRec);
                $data->records = $listOfRec;
            }

            if (! empty($coupons)) {
                $pagetitle = Lang::get('mobileci.page_title.coupon_single') . ': ' . $coupons[0]->promotion_name;
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

            return $this->redirectIfNotLoggedIn($e);
        }
    }


}
