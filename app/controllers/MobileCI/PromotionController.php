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
use Product;
use Promotion;
use stdclass;
use Validator;
use View;

class PromotionController extends MobileCIAPIController
{
    /**
     * GET - Promotion detail page
     *
     * @param string    `promoid`        (required) - The promotion ID
     * @param string    `sort_by`        (optional)
     * @param string    `sort_mode`      (optional)
     *
     * @return \Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            $retailer = $this->getRetailerInfo();

            $products = Product::from(DB::raw(DB::getTablePrefix() . 'products use index(primary)'))->active()
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
            });

            $cartitems = $this->getCartForToolbar();

            $all_promotions = DB::select(
                DB::raw(
                    'SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
                    inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N"
                    left join ' . DB::getTablePrefix() . 'promotion_product propro on (pr.promotion_rule_id = propro.promotion_rule_id AND object_type = "discount")
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

                    WHERE prr.retailer_id = :retailerid OR (p.is_all_retailer = "Y" AND p.merchant_id = :merchantid)'
                    ),
                    array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );
            
            $promotions = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N"
                left join ' . DB::getTablePrefix() . 'promotion_product propro on (pr.promotion_rule_id = propro.promotion_rule_id AND object_type = "discount")
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

                WHERE p.promotion_id = :promid AND (prr.retailer_id = :retailerid OR (p.is_all_retailer = "Y" AND p.merchant_id = :merchantid))
                
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'promid' => $promoid)
            );
            // dd($promotions);
            $product_on_promo = array();
            foreach ($promotions as $promotion) {
                if (empty($promotion->promo_image)) {
                    $promotion->promo_image = 'mobile-ci/images/default_product.png';
                } 
                $product_on_promo[] = $promotion->product_id;
            }
            
            foreach ($promotions as $promotion) {
                if ($promotion->is_all_product_discount === 'N') {
                    if (! empty($product_on_promo)) {
                        $products->whereIn('products.product_id', $product_on_promo);
                    } else {
                        $products->where('product_id', '-1');
                    }
                }
            }

            $couponstocatchs = DB::select(
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
                WHERE prr.retailer_id = :retailerid OR (p.is_all_retailer = "Y" AND p.merchant_id = :merchantid)
                
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );

            $coupons = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid, p.image AS promo_image FROM ' . DB::getTablePrefix() . 'promotions p
                inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.is_coupon = "Y" and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y"))
                left join ' . DB::getTablePrefix() . 'promotion_product propro on (pr.promotion_rule_id = propro.promotion_rule_id AND object_type = "discount")
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
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id)
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

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.per_page');
            if ($perPage <= 0) {
                $perPage = 20;
            }

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $products->take($take);

            $skip = 0;
            OrbitInput::get(
                'skip',
                function ($_skip) use (&$skip, $products) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                }
            );
            $products->skip($skip);

            $next_skip = $skip + $take;

            $totalRec = $_products->count();
            $listOfRec = $products->get();

            $no_more = FALSE;
            if($next_skip >= $totalRec) {
                $next_skip = $totalRec;
                $no_more = TRUE;
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
                    $all_promotions,
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
                            if($v->is_all_product_rule == 'N' || $v->is_all_product_rule === NULL) {
                                return $v->product_id == $product->product_id && $v->maximum_issued_coupon > $issued;
                            } else {
                                return $v;
                            }
                        } else {
                            if($v->is_all_product_rule == 'N' || $v->is_all_product_rule === NULL) {
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
                if (($product->new_from <= \Carbon\Carbon::now() && $product->new_until >= \Carbon\Carbon::now()) || ($product->new_from <= \Carbon\Carbon::now() && $product->new_from !== '0000-00-00 00:00:00' && $product->new_until === '0000-00-00 00:00:00')) {
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

            if (! empty($promotions)) {
                $pagetitle = Lang::get('mobileci.page_title.promotion') . ' : ' . $promotions[0]->promotion_name;
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

            return View::make('mobile-ci.promotions', array('page_title'=>$pagetitle, 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo, 'no_more' => $no_more, 'next_skip' => $next_skip));

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

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Promotion listing page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\View\View
     */
    public function getPromotionList()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $promotions = Promotion::with('promotionrule')->active()->where('is_coupon', 'N')
                ->where(function($q) use ($retailer) {
                    $q->where(function($q2) use($retailer) {
                        $q2->where('is_all_retailer', 'Y');
                        $q2->where('merchant_id', $retailer->parent->merchant_id);
                    });
                    $q->orWhere(function($q2) use ($retailer) {
                        $q2->where('is_all_retailer', 'N');
                        $q2->whereHas('retailers', function($q3) use($retailer) {
                            $q3->where('promotion_retailer.retailer_id', $retailer->merchant_id);
                        });
                    });
                })
                ->where(
                    function ($q) {
                        $q->where('begin_date', '<=', Carbon::now())->where('end_date', '>=', Carbon::now())->orWhere(
                            function ($qr) {
                                $qr->where('begin_date', '<=', Carbon::now())->where('is_permanent', '=', 'Y');
                            }
                        );
                    }
                )
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

            return View::make('mobile-ci.promotion-list', array('page_title' => Lang::get('mobileci.page_title.promotions'), 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems));
        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: %s', 'Promotion List');
            $activityPage->setUser($user)
                ->setActivityName('view_page_promotion_list')
                ->setActivityNameLong('View (Promotion List) Failed')
                ->setObject(null)
                ->setModuleName('Catalogue')
                ->setNotes($activityPageNotes)
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }
}
