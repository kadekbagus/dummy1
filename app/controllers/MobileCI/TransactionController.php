<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Activity;
use Carbon\Carbon as Carbon;
use Cart;
use CartCoupon;
use CartDetail;
use Config;
use Coupon;
use DB;
use Exception;
use IssuedCoupon;
use Lang;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use Product;
use Promotion;
use stdclass;
use Transaction;
use TransactionDetail;
use TransactionDetailCoupon;
use TransactionDetailPromotion;
use TransactionDetailTax;
use UserDetail;
use Validator;
use View;

class TransactionController extends MobileCIAPIController
{
    /**
     * POST - Save transaction and show thankyou page with the receipt
     *
     * @param integer    `payment_method`        (optional) - The payment method
     *
     * @return \Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            $total_to_pay = $cartdata->cartsummary->total_to_pay;

            $merchant_id = $retailer->parent->merchant_id;
            $retailer_id = $retailer->merchant_id;
            $customer_id = $user->user_id;

            $payment_method = OrbitInput::post('payment_method');

            $cart = $cartdata->cart;
            $cartdetails = $cartdata->cartdetails;

            $cart_promotion   = $cartdata->cartsummary->acquired_promo_carts; // data of array
            $cart_coupon      = $cartdata->cartsummary->used_cart_coupons; // data of array

            $cart_id = null;

            if ($payment_method == 'online_payment') {
                $activity_payment = 'online_payment';
                $activity_payment_label = 'Payment Online';
            } elseif ($payment_method == 'paypal') {
                $activity_payment = 'paypal';
                $activity_payment_label = 'Paypal';
            }

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
            $transaction->cashier_id     = null;
            $transaction->customer_id    = $customer_id;
            $transaction->payment_method = $payment_method;
            $transaction->status         = 'paid';
            $transaction->currency       = $retailer->parent->currency;
            $transaction->currency_symbol = $retailer->parent->currency_symbol;

            $transaction->save();

            //insert to table transaction_details
            foreach ($cartdetails as $cart_value) {
                $cart_id = $cart_value->cart->cart_id;
                $transactiondetail = new TransactionDetail();
                $transactiondetail->transaction_id              = $transaction->transaction_id;
                $transactiondetail->product_id                  = $cart_value->product_id;
                $transactiondetail->product_name                = $cart_value->product->product_name;
                $transactiondetail->product_code                = $cart_value->product->product_code;
                $transactiondetail->quantity                    = $cart_value->quantity;
                $transactiondetail->upc                         = $cart_value->product->upc_code;
                $transactiondetail->price                       = $cart_value->product->price;
                $transactiondetail->currency                    = $retailer->parent->currency;

                if (! empty($cart_value->variant)) {
                    $transactiondetail->product_variant_id          = $cart_value->variant->product_variant_id;
                    $transactiondetail->variant_price               = $cart_value->variant->price;
                    $transactiondetail->variant_upc                 = $cart_value->variant->upc;
                    $transactiondetail->variant_sku                 = $cart_value->variant->sku;

                    if (! empty($cart_value->variant->product_attribute_value_id1)) {
                        $transactiondetail->product_attribute_value_id1 = $cart_value->variant->product_attribute_value_id1;
                    }
                    if (! empty($cart_value->variant->product_attribute_value_id2)) {
                        $transactiondetail->product_attribute_value_id2 = $cart_value->variant->product_attribute_value_id2;
                    }
                    if (! empty($cart_value->variant->product_attribute_value_id3)) {
                        $transactiondetail->product_attribute_value_id3 = $cart_value->variant->product_attribute_value_id3;
                    }
                    if (! empty($cart_value->variant->product_attribute_value_id4)) {
                        $transactiondetail->product_attribute_value_id4 = $cart_value->variant->product_attribute_value_id4;
                    }
                    if (! empty($cart_value->variant->product_attribute_value_id5)) {
                        $transactiondetail->product_attribute_value_id5 = $cart_value->variant->product_attribute_value_id5;
                    }

                    if (! empty($cart_value->variant->attributeValue1->value)) {
                        $transactiondetail->product_attribute_value1 = $cart_value->variant->attributeValue1->value;
                    }
                    if (! empty($cart_value->variant->attributeValue2->value)) {
                        $transactiondetail->product_attribute_value2 = $cart_value->variant->attributeValue2->value;
                    }
                    if (! empty($cart_value->variant->attributeValue3->value)) {
                        $transactiondetail->product_attribute_value3 = $cart_value->variant->attributeValue3->value;
                    }
                    if (! empty($cart_value->variant->attributeValue4->value)) {
                        $transactiondetail->product_attribute_value4 = $cart_value->variant->attributeValue4->value;
                    }
                    if (! empty($cart_value->variant->attributeValue5->value)) {
                        $transactiondetail->product_attribute_value5 = $cart_value->variant->attributeValue5->value;
                    }

                    if (! empty($cart_value->variant->attributeValue1->attribute->product_attribute_name)) {
                         $transactiondetail->product_attribute_name1 = $cart_value->variant->attributeValue1->attribute->product_attribute_name;
                    }
                    if (! empty($cart_value->variant->attributeValue2->attribute->product_attribute_name)) {
                         $transactiondetail->product_attribute_name2 = $cart_value->variant->attributeValue2->attribute->product_attribute_name;
                    }
                    if (! empty($cart_value->variant->attributeValue3->attribute->product_attribute_name)) {
                         $transactiondetail->product_attribute_name3 = $cart_value->variant->attributeValue3->attribute->product_attribute_name;
                    }
                    if (! empty($cart_value->variant->attributeValue4->attribute->product_attribute_name)) {
                         $transactiondetail->product_attribute_name4 = $cart_value->variant->attributeValue4->attribute->product_attribute_name;
                    }
                    if (! empty($cart_value->variant->attributeValue5->attribute->product_attribute_name)) {
                         $transactiondetail->product_attribute_name5 = $cart_value->variant->attributeValue5->attribute->product_attribute_name;
                    }
                }

                if (! empty($cart_value->tax1->merchant_tax_id)) {
                    $transactiondetail->merchant_tax_id1 = $cart_value->tax1->merchant_tax_id;
                }
                if (! empty($cart_value->tax2->merchant_tax_id)) {
                    $transactiondetail->merchant_tax_id2 = $cart_value->tax2->merchant_tax_id;
                }

                if (! is_null($cart_value->product->attribute1)) {
                    $transactiondetail->attribute_id1 = $cart_value->product->attribute1->product_attribute_id;
                }
                if (! is_null($cart_value->product->attribute2)) {
                    $transactiondetail->attribute_id2 = $cart_value->product->attribute2->product_attribute_id;
                }
                if (! is_null($cart_value->product->attribute3)) {
                    $transactiondetail->attribute_id3 = $cart_value->product->attribute3->product_attribute_id;
                }
                if (! is_null($cart_value->product->attribute4)) {
                    $transactiondetail->attribute_id4 = $cart_value->product->attribute4->product_attribute_id;
                }
                if (! is_null($cart_value->product->attribute5)) {
                    $transactiondetail->attribute_id5 = $cart_value->product->attribute5->product_attribute_id;
                }

                $transactiondetail->save();

                // product based promotion
                if (! empty($cart_value->promo_for_this_product)) {
                    foreach ($cart_value->promo_for_this_product as $value) {
                        $transactiondetailpromotion = new TransactionDetailPromotion();
                        $transactiondetailpromotion->transaction_detail_id = $transactiondetail->transaction_detail_id;
                        $transactiondetailpromotion->transaction_id = $transaction->transaction_id;
                        $transactiondetailpromotion->promotion_id = $value->promotion_id;
                        $transactiondetailpromotion->promotion_name = $value->promotion_name;

                        if (! empty($value->promotion_type)) {
                            $transactiondetailpromotion->promotion_type = $value->promotion_type;
                        }

                        $transactiondetailpromotion->rule_type = $value->rule_type;

                        if (! empty($value->rule_value)) {
                            $transactiondetailpromotion->rule_value = $value->rule_value;
                        }

                        if (! empty($value->discount_object_type)) {
                            $transactiondetailpromotion->discount_object_type = $value->discount_object_type;
                        }

                        $transactiondetailpromotion->discount_value = $value->discount_value;
                        $transactiondetailpromotion->value_after_percentage = $value->discount;

                        if (! empty($value->description)) {
                            $transactiondetailpromotion->description = $value->description;
                        }

                        if (! empty($value->begin_date)) {
                            $transactiondetailpromotion->begin_date = $value->begin_date;
                        }

                        if (! empty($value->end_date)) {
                            $transactiondetailpromotion->end_date = $value->end_date;
                        }

                        $transactiondetailpromotion->save();

                    }
                }

                // product based coupon
                if (! empty($cart_value->coupon_for_this_product)) {
                    foreach ($cart_value->coupon_for_this_product as $value) {
                            $transactiondetailcoupon = new TransactionDetailCoupon();
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
                        if (! empty($value->issuedcoupon->issued_coupon_id)) {
                            $coupon_id = intval($value->issuedcoupon->issued_coupon_id);
                            $coupon_redeemed = IssuedCoupon::where('issued_coupon_id', $coupon_id)->update(array('status' => 'redeemed'));
                        }
                    }
                }

                // transaction detail taxes
                if (! empty($cartdata->cartsummary->taxes)) {
                    foreach ($cartdata->cartsummary->taxes as $value) {
                        // dd($value);
                        if (! empty($value->total_tax)) {
                            $transactiondetailtax = new TransactionDetailTax();
                            $transactiondetailtax->transaction_detail_id = $transactiondetail->transaction_detail_id;
                            $transactiondetailtax->transaction_id = $transaction->transaction_id;
                            $transactiondetailtax->tax_name = $value->tax_name;
                            $transactiondetailtax->tax_value = $value->tax_value;
                            $transactiondetailtax->tax_order = $value->tax_order;
                            $transactiondetailtax->tax_id = $value->merchant_tax_id;
                            $transactiondetailtax->total_tax = $value->total_tax;
                            $transactiondetailtax->save();
                        }
                    }
                }
            }

            // cart based promotion
            if (! empty($cart_promotion)) {
                foreach ($cart_promotion as $value) {
                    $transactiondetailpromotion = new TransactionDetailPromotion();
                    $transactiondetailpromotion->transaction_detail_id = $transactiondetail->transaction_detail_id;
                    $transactiondetailpromotion->transaction_id = $transaction->transaction_id;
                    $transactiondetailpromotion->promotion_id = $value->promotion_id;
                    $transactiondetailpromotion->promotion_name = $value->promotion_name;
                    $transactiondetailpromotion->promotion_type = $value->promotion_type;
                    $transactiondetailpromotion->rule_type = $value->promotionrule->rule_type;
                    $transactiondetailpromotion->rule_value = $value->promotionrule->rule_value;
                    $transactiondetailpromotion->discount_object_type = $value->promotionrule->discount_object_type;
                    if ($value->promotionrule->rule_type=="cart_discount_by_percentage") {
                        $transactiondetailpromotion->discount_value = $value->promotionrule->discount_value;
                        $transactiondetailpromotion->value_after_percentage = str_replace('-', '', $value->disc_val);
                    } else {
                        $transactiondetailpromotion->discount_value = $value->promotionrule->discount_value;
                        $transactiondetailpromotion->value_after_percentage = str_replace('-', '', $value->disc_val);
                    }
                    $transactiondetailpromotion->description = $value->description;
                    $transactiondetailpromotion->begin_date = $value->begin_date;
                    $transactiondetailpromotion->end_date = $value->end_date;
                    $transactiondetailpromotion->save();

                }
            }

            // cart based coupon
            if (! empty($cart_coupon)) {
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
                    if ($value->issuedcoupon->rule_type == "cart_discount_by_percentage") {
                        $transactiondetailcoupon->discount_value = $value->issuedcoupon->discount_value;
                        $transactiondetailcoupon->value_after_percentage = str_replace('-', '', $value->disc_val);
                    } else {
                        $transactiondetailcoupon->discount_value = $value->issuedcoupon->discount_value;
                        $transactiondetailcoupon->value_after_percentage = str_replace('-', '', $value->disc_val);
                    }
                    $transactiondetailcoupon->coupon_redeem_rule_value = $value->issuedcoupon->coupon_redeem_rule_value;
                    $transactiondetailcoupon->description = $value->issuedcoupon->description;
                    $transactiondetailcoupon->begin_date = $value->issuedcoupon->begin_date;
                    $transactiondetailcoupon->end_date = $value->issuedcoupon->end_date;
                    $transactiondetailcoupon->save();

                    // coupon redeemed
                    if (! empty($value->issuedcoupon->issued_coupon_id)) {
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

                    $coupons = DB::select(
                        DB::raw(
                            'SELECT *, p.promotion_id as promoid FROM ' . DB::getTablePrefix() . 'promotions p
                        inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "Y"
                        left join ' . DB::getTablePrefix() . 'promotion_product propro on (pr.promotion_rule_id = propro.promotion_rule_id AND object_type = "rule")
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
                        WHERE ((prod.product_id = :productid AND pr.is_all_product_rule = "N") OR pr.is_all_product_rule = "Y") AND (prr.retailer_id = :retailerid OR (p.is_all_retailer = "Y" AND p.merchant_id = :merchantid))
                        '
                        ),
                        array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product_id)
                    );
                    
                    if (! empty($coupons)) {
                        foreach ($coupons as $c) {
                            $lastcoupon = IssuedCoupon::orderBy('created_at', 'desc')->first();
                            if ($c->maximum_issued_coupon != 0) {
                                $issued = IssuedCoupon::where('promotion_id', $c->promotion_id)->count();
                                if ($issued < $c->maximum_issued_coupon) {
                                    $issue_coupon = new IssuedCoupon();
                                    $issue_coupon->promotion_id = $c->promotion_id;
                                    $issue_coupon->issued_coupon_code = $lastcoupon->exists() ? ($lastcoupon->issued_coupon_code + 1) : (IssuedCoupon::ISSUE_COUPON_INCREMENT + 1);
                                    $issue_coupon->user_id = $customer_id;
                                    $issue_coupon->expired_date = Carbon::now()->addDays($c->coupon_validity_in_days);
                                    $issue_coupon->issued_date = Carbon::now();
                                    $issue_coupon->issuer_retailer_id = $retailer->merchant_id;
                                    $issue_coupon->status = 'active';
                                    $issue_coupon->transaction_id = $transaction->transaction_id;
                                    $issue_coupon->save();
                                    // $issue_coupon->issued_coupon_code = IssuedCoupon::ISSUE_COUPON_INCREMENT+$issue_coupon->issued_coupon_id;
                                    // $issue_coupon->save();

                                    $acquired_coupon = IssuedCoupon::with('coupon', 'coupon.couponrule', 'coupon.redeemretailers')->where('issued_coupon_id', $issue_coupon->issued_coupon_id)->first();
                                    $acquired_coupons[] = $acquired_coupon;
                                }
                            } else {
                                $issue_coupon = new IssuedCoupon();
                                $issue_coupon->promotion_id = $c->promotion_id;
                                $issue_coupon->issued_coupon_code = $lastcoupon->exists() ? ($lastcoupon->issued_coupon_code + 1) : (IssuedCoupon::ISSUE_COUPON_INCREMENT + 1);
                                $issue_coupon->user_id = $customer_id;
                                $issue_coupon->expired_date = Carbon::now()->addDays($c->coupon_validity_in_days);
                                $issue_coupon->issued_date = Carbon::now();
                                $issue_coupon->issuer_retailer_id = $retailer->merchant_id;
                                $issue_coupon->status = 'active';
                                $issue_coupon->transaction_id = $transaction->transaction_id;
                                $issue_coupon->save();
                                // $issue_coupon->issued_coupon_code = IssuedCoupon::ISSUE_COUPON_INCREMENT+$issue_coupon->issued_coupon_id;
                                // $issue_coupon->save();

                                $acquired_coupon = IssuedCoupon::with('coupon', 'coupon.couponrule', 'coupon.redeemretailers')->where('issued_coupon_id', $issue_coupon->issued_coupon_id)->first();
                                $acquired_coupons[] = $acquired_coupon;
                            }
                        }
                    }
                }
            }

            // issue cart based coupons (if any)
            if (! empty($customer_id)) {
                $coupon_carts = Coupon::join(
                    'promotion_rules',
                    function ($q) use ($total_to_pay) {
                        $q->on('promotions.promotion_id', '=', 'promotion_rules.promotion_id')->where('promotion_rules.rule_value', '<=', $total_to_pay);
                    }
                )->active()->where('promotion_type', 'cart')
                // ->where('merchant_id', $retailer->parent_id)
                // ->whereHas(
                //     'issueretailers',
                //     function ($q) use ($retailer) {
                //             $q->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                //     }
                // )
                ->where(function($q) use ($retailer) {
                    $q->where(function($q2) use($retailer) {
                        $q2->where('is_all_retailer', 'Y');
                        $q2->where('merchant_id', $retailer->parent->merchant_id);
                    });
                    $q->orWhere(function($q2) use ($retailer) {
                        $q2->where('is_all_retailer', 'N');
                        $q2->whereHas('issueretailers', function($q3) use($retailer) {
                            $q3->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                        });
                    });
                })
                ->get();

                if (! empty($coupon_carts)) {
                    foreach ($coupon_carts as $kupon) {
                        $lastcoupon = IssuedCoupon::orderBy('created_at', 'desc')->first();
                        if ($kupon->maximum_issued_coupon != 0) {
                            $issued = IssuedCoupon::where('promotion_id', $kupon->promotion_id)->count();
                            if ($issued < $kupon->maximum_issued_coupon) {
                                $issue_coupon = new IssuedCoupon();
                                $issue_coupon->promotion_id = $kupon->promotion_id;
                                $issue_coupon->issued_coupon_code = $lastcoupon->exists() ? ($lastcoupon->issued_coupon_code + 1) : (IssuedCoupon::ISSUE_COUPON_INCREMENT + 1);
                                $issue_coupon->user_id = $customer_id;
                                $issue_coupon->expired_date = Carbon::now()->addDays($kupon->coupon_validity_in_days);
                                $issue_coupon->issued_date = Carbon::now();
                                $issue_coupon->issuer_retailer_id = $retailer->merchant_id;
                                $issue_coupon->status = 'active';
                                $issue_coupon->transaction_id = $transaction->transaction_id;
                                $issue_coupon->save();
                                // $issue_coupon->issued_coupon_code = IssuedCoupon::ISSUE_COUPON_INCREMENT+$issue_coupon->issued_coupon_id;
                                // $issue_coupon->save();

                                $acquired_coupon = IssuedCoupon::with('coupon', 'coupon.couponrule', 'coupon.redeemretailers')->where('issued_coupon_id', $issue_coupon->issued_coupon_id)->first();
                                $acquired_coupons[] = $acquired_coupon;
                            }
                        } else {
                            $issue_coupon = new IssuedCoupon();
                            $issue_coupon->promotion_id = $kupon->promotion_id;
                            $issue_coupon->issued_coupon_code = $lastcoupon->exists() ? ($lastcoupon->issued_coupon_code + 1) : (IssuedCoupon::ISSUE_COUPON_INCREMENT + 1);
                            $issue_coupon->user_id = $customer_id;
                            $issue_coupon->expired_date = Carbon::now()->addDays($kupon->coupon_validity_in_days);
                            $issue_coupon->issued_date = Carbon::now();
                            $issue_coupon->issuer_retailer_id = $retailer->merchant_id;
                            $issue_coupon->status = 'active';
                            $issue_coupon->transaction_id = $transaction->transaction_id;
                            $issue_coupon->save();
                            // $issue_coupon->issued_coupon_code = IssuedCoupon::ISSUE_COUPON_INCREMENT+$issue_coupon->issued_coupon_id;
                            // $issue_coupon->save();

                            $acquired_coupon = IssuedCoupon::with('coupon', 'coupon.couponrule', 'coupon.redeemretailers')->where('issued_coupon_id', $issue_coupon->issued_coupon_id)->first();
                            $acquired_coupons[] = $acquired_coupon;
                        }
                    }
                }
            }

            // delete the cart
            if (! empty($cart_id)) {
                $cart_delete = Cart::where('status', 'active')->where('cart_id', $cart_id)->first();
                $cart_delete->delete();
                $cart_delete->save();
                $cart_detail_delete = CartDetail::where('status', 'active')->where('cart_id', $cart_id)->update(array('status' => 'deleted'));
            }

            // generate receipt
            $transaction = Transaction::with('details', 'detailcoupon', 'detailpromotion', 'cashier', 'user')->where('transaction_id', $transaction->transaction_id)->first();
            $issuedcoupon = IssuedCoupon::with('coupon.couponrule', 'coupon.redeemretailers')->where('transaction_id', $transaction->transaction_id)->get();

            $details = $transaction->details->toArray();
            $detailcoupon = $transaction->detailcoupon->toArray();

            $detailpromotion = $transaction->detailpromotion->toArray();
            $_issuedcoupon = $issuedcoupon->toArray();
            $total_issuedcoupon = count($_issuedcoupon);
            $acquired_coupon = null;

            foreach ($_issuedcoupon as $key => $value) {
                // $date  =  $transaction['created_at']->timezone(Config::get('app.timezone'))->format('d M Y H:i:s');
                $datex = Carbon::parse($value['expired_date'])->timezone(Config::get('app.timezone'))->format('d M Y H:i');
                if ($key == 0) {
                    $acquired_coupon .= " \n";
                    $acquired_coupon .= '----------------------------------------' . " \n";
                    $acquired_coupon .=  $this->just40CharMid('Acquired Coupon');
                    $acquired_coupon .= '----------------------------------------' . " \n";
                    $acquired_coupon .= $this->just40CharMid($value['coupon']['promotion_name']);
                    $acquired_coupon .= $this->just40CharMid($value['coupon']['description']);
                    $acquired_coupon .= $this->just40CharMid("Coupon Code " . $value['issued_coupon_code']);
                    $acquired_coupon .= $this->just40CharMid("Valid until " . $datex);
                } else {
                    $acquired_coupon .= '----------------------------------------' . " \n";
                    $acquired_coupon .= $this->just40CharMid($value['coupon']['promotion_name']);
                    $acquired_coupon .= $this->just40CharMid($value['coupon']['description']);
                    $acquired_coupon .= $this->just40CharMid("Coupon Code " . $value['issued_coupon_code']);
                    $acquired_coupon .= $this->just40CharMid("Valid until " . $datex);
                    if ($key == $total_issuedcoupon-1) {
                        $acquired_coupon .= '----------------------------------------' . " \n";
                    }
                }
            }

            foreach ($details as $details_key => $details_value) {
                if ($details_key==0) {
                    $product = $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['variant_price'], $details_value['quantity'], $details_value['variant_sku']);
                } else {
                    $product .= $this->productListFormat(substr($details_value['product_name'], 0, 25), $details_value['variant_price'], $details_value['quantity'], $details_value['variant_sku']);
                }

                foreach ($detailpromotion as $detailpromotion_key => $detailpromotion_value) {
                    if ($details_value['transaction_detail_id'] == $detailpromotion_value['transaction_detail_id'] && $detailpromotion_value['promotion_type'] == 'product') {
                        $product .= $this->discountListFormat(substr($detailpromotion_value['promotion_name'], 0, 25), $detailpromotion_value['value_after_percentage']);
                    }
                }

                foreach ($detailcoupon as $detailcoupon_key => $detailcoupon_value) {
                    if ($details_value['transaction_detail_id'] == $detailcoupon_value['transaction_detail_id'] && ($detailcoupon_value['promotion_type'] == 'product' || ($detailcoupon_value['promotion_type'] == 'cart' && $detailcoupon_value['discount_object_type']!='cash_rebate' ))) {
                        $product .= $this->discountListFormat(substr($detailcoupon_value['promotion_name'], 0, 25), $detailcoupon_value['value_after_percentage']);
                    }
                }
            }

            $product .= '----------------------------------------' . " \n";

            $promo = false;

            foreach ($details as $details_key => $details_value) {
                $x = 0;
                foreach ($detailpromotion as $detailpromotion_key => $detailpromotion_value) {
                    if ($details_value['transaction_detail_id'] == $detailpromotion_value['transaction_detail_id'] && $detailpromotion_value['promotion_type'] == 'cart') {
                        if ($x==0) {
                            $cart_based_promo = "Cart Promotions" . " \n";
                            $promo = true;
                        }
                        $x = $x+1;
                        $promo = true;
                        $cart_based_promo .= $this->discountListFormat(substr($detailpromotion_value['promotion_name'], 0, 23), $detailpromotion_value['value_after_percentage']);
                    }
                }
            }

            foreach ($details as $details_key => $details_value) {
                $x = 0;
                foreach ($detailcoupon as $detailcoupon_key => $detailcoupon_value) {
                    if ($details_value['transaction_detail_id'] == $detailcoupon_value['transaction_detail_id'] && $detailcoupon_value['promotion_type'] == 'cart' && $detailcoupon_value['discount_object_type'] == 'cash_rebate') {
                        if ($x==0) {
                            if (!$promo) {
                                $cart_based_promo = "Cart Coupons" . " \n";
                                $promo = true;
                            } else {
                                $cart_based_promo .= "Cart Coupons" . " \n";
                            }

                        }
                        $x = $x+1;
                        $promo = true;
                        $cart_based_promo .= $this->discountListFormat(substr($detailcoupon_value['promotion_name'], 0, 23), $detailcoupon_value['value_after_percentage']);
                    }
                }
            }

            if ($promo) {
                $cart_based_promo .= '----------------------------------------' . " \n";
            }

            $payment = $transaction['payment_method'];
            if ($payment=='cash') {
                $payment='Cash';
            }

            if ($payment=='card') {
                $payment='Card';
            }

            if ($payment=='online_payment') {
                $payment='Online Payment';
            }

            if ($payment=='paypal') {
                $payment='Paypal';
            }

            $date  =  $transaction['created_at']->timezone(Config::get('app.timezone'))->format('d M Y H:i:s');
            // $date = date('d M Y H:i:s', strtotime($transaction['created_at']));

            if ($transaction['user']==null) {
                $customer = "Guest";
            } else {
                if (! empty($transaction['user']->user_firstname) && ! empty($transaction['user']->user_lastname)) {
                    $customer = $transaction['user']->user_firstname . ' ' . $transaction['user']->user_lastname;
                } else {
                    $customer = $transaction['user']->user_email;
                }

            }

            $bill_no = $transaction['transaction_id'];

            $head  = " \n";
            $head .= " \n";
            $head .= $this->just40CharMid($retailer->parent->name);
            $head .= $this->just40CharMid($retailer->name);
            $head .= $this->just40CharMid($retailer->address_line1)."\n";

            // ticket header
            $ticket_header = $retailer->parent->ticket_header;
            $ticket_header_line = explode("\n", $ticket_header);
            foreach ($ticket_header_line as $line => $value) {
                $head .= $this->just40CharMid($value);
            }
            $head .= '----------------------------------------' . " \n";
            $head .= 'Date : ' . $date." \n";
            $head .= 'Bill No  : ' . $bill_no." \n";
            $head .= 'Customer : ' . $customer." \n";

            $head .= '----------------------------------------' . " \n";

            $pay   = $this->leftAndRight('SUB TOTAL', number_format($transaction['subtotal'], 2));
            $pay  .= $this->leftAndRight('TAX', number_format($transaction['vat'], 2));
            $pay  .= $this->leftAndRight('TOTAL', number_format($transaction['total_to_pay'], 2));
            $pay  .= " \n";

            foreach ($cartdata->cartsummary->taxes as $tax) {
                if (! empty($tax->total_tax) && $tax->total_tax > 0) {
                    $pay  .= $this->leftAndRight($tax->tax_name . '(' . ($tax->tax_value * 100) . '%)', number_format($tax->total_tax, 2));
                }
            }
            $pay  .= " \n";
            $pay  .= $this->leftAndRight('Payment Method', $payment);
            if ($payment=='Cash') {
                $pay  .= $this->leftAndRight('Tendered', number_format($transaction['tendered'], 2));
                $pay  .= $this->leftAndRight('Change', number_format($transaction['change'], 2));
            }
            if ($payment=="Card") {
                $pay  .= $this->leftAndRight('Total Paid', number_format($transaction['total_to_pay'], 2));
            }
            $pay  .= $this->leftAndRight('Amount in', $transaction->currency);
            $footer  = " \n";
            $footer .= " \n";
            $footer .= " \n";

            // ticket footer
            $ticket_footer = $retailer->parent->ticket_footer;
            $ticket_footer_line = explode("\n", $ticket_footer);
            foreach ($ticket_footer_line as $line => $value) {
                $footer .= $this->just40CharMid($value);
            }

            $footer .= " \n";
            $footer .= " \n";
            $footer .= " \n";
            $footer .= $this->just40CharMid('Powered by DominoPos');
            $footer .= $this->just40CharMid('www.dominopos.com');
            $footer .= '----------------------------------------' . " \n";

            $footer .= " \n";
            $footer .= " \n";
            $footer .= " \n";

            $transaction_date = str_replace(' ', '_', $transaction->created_at);
            $transaction_date = str_replace(':', '', $transaction->created_at);

            $tr_date = strtotime($transaction_date);
            $_tr_date = date('d-m-Y H-i-s', $tr_date);

            // Example Result: recipt-123-2015-03-04_101010.txt
            $attachment_name = sprintf('receipt-%s_%s.png', $transaction->transaction_id, $_tr_date);

            if (! empty($cart_based_promo)) {
                $write = $head.$product.$cart_based_promo.$pay.$acquired_coupon.$footer;
            } else {
                $write = $head.$product.$pay.$acquired_coupon.$footer;
            }

            $fontsize = 12;

            $font_path = public_path() . '/templatepos/courier.ttf';
            $size = imagettfbbox($fontsize, 0, $font_path, $write);
            $xsize = abs($size[0]) + abs($size[2]);
            $ysize = abs($size[5]) + abs($size[1]);

            $image = imagecreate($xsize, $ysize);
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = ImageColorAllocate($image, 0, 0, 0);
            imagettftext($image, $fontsize, 0, abs($size[0]), abs($size[5]), $black, $font_path, $write);

            ob_start();

              $image_data = imagepng($image);
              $image_data = ob_get_contents();

            ob_end_clean();

            $base64receipt = base64_encode($image_data);

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

            return View::make('mobile-ci.thankyou', array('retailer'=>$retailer, 'cartdata' => $cartdata, 'transaction' => $transaction, 'acquired_coupons' => $acquired_coupons, 'base64receipt' => $base64receipt, 'ticket_format' => $attachment_name));

        } catch (Exception $e) {
            $this->rollback();
            $activity->setUser($user)
                ->setActivityName($activity_payment)
                ->setActivityNameLong($activity_payment . ' Failed')
                ->setObject(null)
                ->setModuleName('Cart')
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Check cart status on transfer cart page
     *
     * @param integer    `cartcode`        (required) - The cart code
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postCloseCart()
    {
        try {
            $cartcode = OrbitInput::post('cartcode');

            $cart = Cart::where('cart_code', $cartcode)->first();

            if ($cart->status === 'cashier') {
                $this->response->message = 'moved';
            } else {
                $this->response->message = 'notmoved';
            }

            return $this->render();
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Cart page
     *
     * @param integer    `from`        (optional) - flag to save or not to save activity
     *
     * @return \Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            return View::make('mobile-ci.cart', array('page_title' => Lang::get('mobileci.page_title.cart'), 'retailer' => $retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata));
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

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Transfer cart page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\View\View
     */
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

    /**
     * POST - Add item to cart
     *
     * @param integer    `productid`        (required) - The product ID
     * @param integer    `productvariantid` (required) - The product variant ID
     * @param integer    `qty`              (required) - The quantity of the product
     * @param array      `coupons`          (optional) - Product based coupons that added to cart
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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
                $cart = new Cart();
                $cart->customer_id = $user->user_id;
                $cart->merchant_id = $retailer->parent_id;
                $cart->retailer_id = $retailer->merchant_id;
                $cart->status = 'active';
                $cart->save();
                $cart->cart_code = Cart::CART_INCREMENT + $cart->cart_id;
                $cart->save();
            }

            $product = Product::with('tax1', 'tax2')
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

            $cart->total_item = $cart->total_item + 1;

            $cart->save();

            $cartdetail = CartDetail::active()->where('product_id', $product_id)->where('product_variant_id', $product_variant_id)->where('cart_id', $cart->cart_id)->first();
            if (empty($cartdetail)) {
                $cartdetail = new CartDetail();
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
                ->join(
                    'promotion_rules',
                    function ($join) use ($merchant_id, $prefix) {
                        $join->on('promotion_rules.promotion_id', '=', 'promotions.promotion_id');
                        $join->where('promotions.promotion_type', '=', 'product');
                        $join->where('promotions.status', '=', 'active');
                        $join->where('promotions.is_coupon', '=', 'N');
                        $join->where('promotions.merchant_id', '=', $merchant_id);
                        $join->on(
                            DB::raw("(({$prefix}promotions.begin_date <= NOW() AND {$prefix}promotions.end_date >= NOW())"),
                            'OR',
                            DB::raw("({$prefix}promotions.begin_date <= NOW() AND {$prefix}promotions.is_permanent = 'Y'))")
                        );
                    }
                )
                ->join(
                    'promotion_retailer',
                    function ($join) use ($retailer_id) {
                        $join->on('promotion_retailer.promotion_id', '=', 'promotions.promotion_id');
                        $join->where('promotion_retailer.retailer_id', '=', $retailer_id);
                    }
                )
                ->join(
                    'products',
                    DB::raw("(({$prefix}promotion_rules.discount_object_type=\"product\" AND {$prefix}promotion_rules.discount_object_id1={$prefix}products.product_id)"),
                    'OR',
                    DB::raw(
                        "                    (
                            ({$prefix}promotion_rules.discount_object_type=\"family\") AND
                            (({$prefix}promotion_rules.discount_object_id1 IS NULL) OR ({$prefix}promotion_rules.discount_object_id1={$prefix}products.category_id1)) AND
                            (({$prefix}promotion_rules.discount_object_id2 IS NULL) OR ({$prefix}promotion_rules.discount_object_id2={$prefix}products.category_id2)) AND
                            (({$prefix}promotion_rules.discount_object_id3 IS NULL) OR ({$prefix}promotion_rules.discount_object_id3={$prefix}products.category_id3)) AND
                            (({$prefix}promotion_rules.discount_object_id4 IS NULL) OR ({$prefix}promotion_rules.discount_object_id4={$prefix}products.category_id4)) AND
                            (({$prefix}promotion_rules.discount_object_id5 IS NULL) OR ({$prefix}promotion_rules.discount_object_id5={$prefix}products.category_id5))
                        ))"
                    )
                )->where('products.product_id', $product_id)->get();

            $variant_price = $product->variants->find($product_variant_id)->price;
            $price_after_promo = $variant_price;

            $activityPromoObj = null;
            $temp_price = $variant_price;
            foreach ($promo_products as $promo) {
                if ($promo->promotionrule->rule_type == 'product_discount_by_percentage' || $promo->promotionrule->rule_type == 'cart_discount_by_percentage') {
                    $discount = $promo->promotionrule->discount_value * $variant_price;
                    if ($temp_price < $discount) {
                        $discount = $temp_price;
                    }
                    $price_after_promo = $price_after_promo - $discount;
                } elseif ($promo->promotionrule->rule_type == 'product_discount_by_value' || $promo->promotionrule->rule_type == 'cart_discount_by_value') {
                    $discount = $promo->promotionrule->discount_value;
                    if ($temp_price < $discount) {
                        $discount = $temp_price;
                    }
                    $price_after_promo = $price_after_promo - $discount;
                } elseif ($promo->promotionrule->rule_type == 'new_product_price') {
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

                $cartcoupon = new CartCoupon();
                $cartcoupon->issued_coupon_id = $coupon;
                $cartcoupon->object_type = 'cart_detail';
                $cartcoupon->object_id = $cartdetail->cart_detail_id;
                $cartcoupon->save();
                $used_coupons->status = 'deleted';
                $used_coupons->save();
                $activityCoupon[] = $used_coupons;
            }

            $coupons = DB::select(
                DB::raw(
                    'SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
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
                WHERE ic.expired_date >= "' . Carbon::now() . '" AND p.merchant_id = :merchantid AND prr.retailer_id = :retailerid AND ic.user_id = :userid AND prod.product_id = :productid AND ic.expired_date >= "' . Carbon::now() . '"'
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $product->product_id)
            );

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
            $activityCartNotes = sprintf('Add to cart: %s', $product_id);
            $activityCart->setUser($user)
                ->setActivityName('add_to_cart')
                ->setActivityNameLong('Add To Cart Failed')
                ->setObject(null)
                ->setModuleName('Cart')
                ->setNotes($activityCartNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }

        return $this->render();
    }

    /**
     * POST - Add product based coupon to cart on cart page
     *
     * @param integer    `productid`        (required) - The product ID
     * @param integer    `productvariantid` (required) - The product variant ID
     * @param array      `coupons`          (optional) - Product based coupons that added to cart
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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
                $cart = new Cart();
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

            if (! empty($cartdetail)) {
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

                    $cartcoupon = new CartCoupon();
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

            $activityCartNotes = sprintf('Use Coupon Failed : %s', $used_coupon_id);
            $activityCart->setUser($user)
                ->setActivityName('use_coupon')
                ->setActivityNameLong('Add Coupon To Cart Failed')
                ->setObject(null)
                ->setModuleName('Coupon')
                ->setNotes($activityCartNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }

        return $this->render();
    }

    /**
     * POST - Add cart based coupon to cart on cart page
     *
     * @param integer    `detail`        (required) - The issued coupon ID
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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
                $cart = new Cart();
                $cart->customer_id = $user->user_id;
                $cart->merchant_id = $retailer->parent_id;
                $cart->retailer_id = $retailer->merchant_id;
                $cart->status = 'active';
                $cart->save();
                $cart->cart_code = Cart::CART_INCREMENT + $cart->cart_id;
                $cart->save();
            }

            $used_coupons = IssuedCoupon::active()->where('issued_coupon_id', $couponid)->first();

            $cartcoupon = new CartCoupon();
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

            return $this->redirectIfNotLoggedIn($e);
        }

        return $this->render();
    }

    /**
     * POST - Delete item from cart
     *
     * @param integer    `detail`        (required) - The cart detail ID
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            // re-activate used coupons on the deleted cart detail
            if (! empty($cartcoupons)) {
                foreach ($cartcoupons as $cartcoupon) {
                    $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $cartcoupon->issued_coupon_id)->first();
                    $issuedcoupon->makeActive();
                    $issuedcoupon->save();
                    $cartcoupon->delete(true);
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

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Reset cart
     *
     * @param integer    `cartid`        (required) - The cart ID
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postResetCart()
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

            $cartid = OrbitInput::post('cartid');

            $validator = \Validator::make(
                array(
                    'cartid' => $cartid,
                ),
                array(
                    'cartid' => 'required|orbit.exists.cartid',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->beginTransaction();

            $cart = Cart::where('cart_id', $cartid)->where('customer_id', $user->user_id)->where('retailer_id', $retailer->merchant_id)->active()->first();

            $cartdetails = CartDetail::where('cart_id', $cart->cart_id)->active()->get();

            $cartbasedcoupons = CartCoupon::where('object_type', 'cart')->where('object_id', $cart->cart_id)->get();

            // re-activate used cart based coupon for the deleted cart
            foreach ($cartbasedcoupons as $cartbasedcoupon) {
                $issuedcartcoupon = IssuedCoupon::where('issued_coupon_id', $cartbasedcoupon->issued_coupon_id)->first();
                $issuedcartcoupon->makeActive();
                $issuedcartcoupon->save();
                $cartbasedcoupon->delete(true);
            }

            // re-activate used product based coupon for the deleted cart
            foreach ($cartdetails as $cartdetail) {
                $cartcoupons = CartCoupon::where('object_type', 'cart_detail')->where('object_id', $cartdetail->cart_detail_id)->get();
                if (! empty($cartcoupons)) {
                    foreach ($cartcoupons as $cartcoupon) {
                        $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $cartcoupon->issued_coupon_id)->first();
                        $issuedcoupon->makeActive();
                        $issuedcoupon->save();
                        $cartcoupon->delete(true);
                    }
                }
                $cartdetail->delete();
                $cartdetail->save();
            }
            $cart->delete();
            $cart->save();

            $cartdata = new stdclass();
            // $cartdata->cart = $cart;
            $this->response->message = 'success';
            $this->response->data = $cartdata;

            $activityPageNotes = sprintf('Cart Reset. Cart id: %s', $cartid);
            $activityPage->setUser($user)
                ->setActivityName('delete_cart')
                ->setActivityNameLong('Reset Cart')
                ->setObject($cart)
                ->setModuleName('Cart')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            $this->commit();

            return $this->render();

        } catch (Exception $e) {
            $this->rollback();
            $activityPageNotes = sprintf('Failed to reset cart. Cart id: %s', $cartid);
            $activityPage->setUser($user)
                ->setActivityName('delete_cart')
                ->setActivityNameLong('Failed To Reset Cart')
                ->setObject(null)
                ->setModuleName('Cart')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Delete coupon from cart
     *
     * @param integer    `detail`        (required) - The issued coupon ID
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            $cartcoupon = CartCoupon::whereHas(
                'issuedcoupon',
                function ($q) use ($user, $issuedcouponid) {
                    $q->where('issued_coupons.user_id', $user->user_id)->where('issued_coupons.issued_coupon_id', $issuedcouponid);
                }
            )->first();

            // re-activate coupon
            if (! empty($cartcoupon)) {
                $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $cartcoupon->issued_coupon_id)->first();
                $issuedcoupon->makeActive();
                $issuedcoupon->save();
                $couponid = $issuedcoupon->coupon->promotion_id;
                $cartcoupon->delete(true);
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

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * POST - Update cart detail quantity
     *
     * @param integer    `detail`        (required) - The cart detail ID
     * @param integer    `qty`           (required) - The new quantity
     *
     * @return \Illuminate\Support\Facades\Response
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Payment page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\View\View
     */
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

            $activityPageNotes = sprintf('Page viewed: %s', 'Online Payment');
            $activityPage->setUser($user)
                ->setActivityName('view_page_online_payment')
                ->setActivityNameLong('View (Online Payment Page)')
                ->setObject(null)
                ->setModuleName('Cart')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            if (! empty($cartitems)) {
                return View::make('mobile-ci.payment', array('page_title'=>Lang::get('mobileci.page_title.payment'), 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata));
            } else {
                return \Redirect::to('/customer/home');
            }

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

    /**
     * GET - Paypal payment page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\View\View
     */
    public function getPaypalPaymentView()
    {
        $user = null;
        $activityPage = Activity::mobileci()
            ->setActivityType('view');
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartitems = $this->getCartForToolbar();

            $cartdata = $this->cartCalc($user, $retailer);

            $activityPageNotes = sprintf('Page viewed: %s', 'Paypal Payment');
            $activityPage->setUser($user)
                ->setActivityName('view_page_paypal_payment')
                ->setActivityNameLong('View (Paypal Payment Page)')
                ->setObject(null)
                ->setModuleName('Cart')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            if (! empty($cartitems)) {
                return View::make('mobile-ci.paypal', array('page_title'=>Lang::get('mobileci.page_title.payment'), 'retailer'=>$retailer, 'cartitems' => $cartitems, 'cartdata' => $cartdata));
            } else {
                return \Redirect::to('/customer/home');
            }

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Paypal Payment');
            $activityPage->setUser($user)
                ->setActivityName('view_page_paypal_payment')
                ->setActivityNameLong('View (Paypal Payment) Failed')
                ->setObject(null)
                ->setModuleName('Cart')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }
}
