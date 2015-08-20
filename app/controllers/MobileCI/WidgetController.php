<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Activity;
use Carbon\Carbon as Carbon;
use CartCoupon;
use Config;
use Coupon;
use DB;
use EventModel;
use Exception;
use Lang;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use Product;
use Promotion;
use Validator;
use View;

class WidgetController extends MobileCIAPIController
{
    /**
     * POST - Pop up for product on cart page
     *
     * @param integer    `detail`        (required) - THe product ID
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            $product = Product::active()
            ->where(function($q) use ($retailer) {
                $q->where(function($q2) use($retailer) {
                    $q2->where('is_all_retailer', 'Y');
                    $q2->where('merchant_id', $retailer->parent->merchant_id);
                });
                $q->orWhere(function($q2) use ($retailer) {
                    $q2->where('is_all_retailer', 'N');
                    $q2->whereHas('retailers', function($q3) use($retailer) {
                        $q3->where('product_retailer.retailer_id', $retailer->merchant_id);
                    });
                });
            })
            ->where('product_id', $product_id)->first();

            $this->response->message = 'success';
            $this->response->data = $product;

            return $this->render();
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Pop up for promotion pop up on cart page
     *
     * @param integer    `promotion_detail`        (required) - The promotion ID
     *
     * @return \Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            return $this->render();
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Pop up for coupon pop up on cart page
     *
     * @param integer    `promotion_detail`        (required) - The coupon ID
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            return $this->render();
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Pop up for product based coupon
     *
     * @param integer    `productid`        (required) - The product ID
     * @param integer    `productvariantid` (required) - The product variant ID
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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
            $promo_products = DB::select(
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

                WHERE prr.retailer_id = :retailerid OR (p.is_all_retailer = "Y" AND p.merchant_id = :merchantid) AND ((prod.product_id = :productid AND pr.is_all_product_discount = "N") OR pr.is_all_product_discount = "Y")
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product_id)
            );

            // ------------------------------- this block is unnecessary ---------------------------
            $promo_percentage_cumulative = 0;
            $promo_for_this_product = array_filter(
                $promo_products,
                function ($v) use ($product_id) {
                    return $v->product_id == $product_id;
                }
            );
            if (count($promo_for_this_product) > 0) {
                foreach ($promo_for_this_product as $promotion) {
                    if ($promotion->rule_type == 'product_discount_by_percentage' || $promotion->rule_type == 'cart_discount_by_percentage') {
                        $promo_percentage_cumulative = $promo_percentage_cumulative + $promotion->discount_value;
                    }
                }
            }

            // count product discount by percentage, shouldn't have more than 100%.
            $coupon_counters = CartCoupon::whereHas(
                'issuedcoupon',
                function ($q) use ($user, $product_id, $product_variant_id) {
                    $q->where('issued_coupons.user_id', $user->user_id);
                    $q->whereHas(
                        'coupon',
                        function ($q2) {
                            $q2->whereHas(
                                'couponrule',
                                function ($q3) {
                                    $q3->where(
                                        function ($q4) {
                                            $q4->where('promotion_rules.rule_type', 'product_discount_by_percentage')->orWhere('promotion_rules.rule_type', 'cart_discount_by_percentage');
                                        }
                                    );
                                }
                            );
                        }
                    );
                }
            )->whereHas(
                'cartdetail',
                function ($q4) use ($product_variant_id) {
                    $q4->where('cart_details.product_variant_id', $product_variant_id);
                }
            )->with('issuedcoupon.coupon.couponrule')->get();

            $coupon_percentage_cumulative = 0;
            foreach ($coupon_counters as $coupon_counter) {
                $coupon_percentage_cumulative = $coupon_percentage_cumulative + $coupon_counter->issuedcoupon->coupon->couponrule->discount_value;
            }

            $percentage_prevent = '';
            // -----------------------------------------------------------------------------------------

            $coupons = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
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
                WHERE ic.expired_date >= "' . Carbon::now() . '" AND ic.user_id = :userid AND ic.expired_date >= "' . Carbon::now() . '" AND (prr.retailer_id = :retailerid OR (p.is_all_retailer_redeem = "Y" AND p.merchant_id = :merchantid)) AND ((prod.product_id = :productid AND pr.is_all_product_discount = "N") OR pr.is_all_product_discount = "Y")
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $product_id)
            );

            foreach ($coupons as $coupon) {
                if (empty($coupon->promo_image)) {
                    $coupon->promo_image = 'mobile-ci/images/default_product.png';
                }
            }

            $this->response->message = 'success';
            $this->response->data = $coupons;

            return $this->render();
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Pop up for product based coupon on cart page
     *
     * @param integer    `promotion_detail`        (required) - The promotion ID
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            return $this->render();
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Event pop up click activity
     *
     * @param integer    `eventdata`        (optional) - The event ID
     *
     * @return void
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            return $this->redirectIfNotLoggedIn($e);
        }
    }
}
