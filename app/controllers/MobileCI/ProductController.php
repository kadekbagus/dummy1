<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Activity;
use Carbon\Carbon as Carbon;
use Category;
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

class ProductController extends MobileCIAPIController
{
    /**
     * GET - Product detail page
     *
     * @param integer    `id`        (required) - The product ID
     *
     * @return \Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getProductView()
    {
        $user = null;
        $product_id = 0;
        $activityProduct = Activity::mobileci()
                                   ->setActivityType('view');
        $product = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();
            $product_id = trim(OrbitInput::get('id'));
            // $product_only = Product::where('product_id', $product_id)->active()->first();
            $cartitems = $this->getCartForToolbar();

            $product = Product::with('variants', 'attribute1', 'attribute2', 'attribute3', 'attribute4', 'attribute5')
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
            ->active()->where('product_id', $product_id)->first();

            if (empty($product)) {
                // throw new Exception('Product id ' . $product_id . ' not found');
                return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer, 'cartitems' => $cartitems));
            }
            
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
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product->product_id)
            );
            
            $couponstocatchs = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid FROM ' . DB::getTablePrefix() . 'promotions p
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
                WHERE ((prod.product_id = :productid AND pr.is_all_product_rule = "N") OR pr.is_all_product_rule = "Y") AND (prr.retailer_id = :retailerid OR (p.is_all_retailer = "Y" AND p.merchant_id = :merchantid))
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product->product_id)
            );
            
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
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $product->product_id)
            );

            $attributes = DB::select(
                DB::raw(
                    'SELECT v.upc, v.sku, v.product_variant_id, av1.value as value1, av1.product_attribute_value_id as attr_val_id1, av2.product_attribute_value_id as attr_val_id2, av3.product_attribute_value_id as attr_val_id3, av4.product_attribute_value_id as attr_val_id4, av5.product_attribute_value_id as attr_val_id5, av2.value as value2, av3.value as value3, av4.value as value4, av5.value as value5, v.price, pa1.product_attribute_name as attr1, pa2.product_attribute_name as attr2, pa3.product_attribute_name as attr3, pa4.product_attribute_name as attr4, pa5.product_attribute_name as attr5 FROM ' . DB::getTablePrefix() . 'product_variants v
                inner join ' . DB::getTablePrefix() . 'products p on p.product_id = v.product_id AND p.status = "active" AND v.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av1 on av1.product_attribute_value_id = v.product_attribute_value_id1 AND av1.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av2 on av2.product_attribute_value_id = v.product_attribute_value_id2 AND av2.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av3 on av3.product_attribute_value_id = v.product_attribute_value_id3 AND av3.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av4 on av4.product_attribute_value_id = v.product_attribute_value_id4 AND av4.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av5 on av5.product_attribute_value_id = v.product_attribute_value_id5 AND av5.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa1 on pa1.product_attribute_id = av1.product_attribute_id AND pa1.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa2 on pa2.product_attribute_id = av2.product_attribute_id AND pa2.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa3 on pa3.product_attribute_id = av3.product_attribute_id AND pa3.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa4 on pa4.product_attribute_id = av4.product_attribute_id AND pa4.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa5 on pa5.product_attribute_id = av5.product_attribute_id AND pa5.status = "active"
                WHERE p.product_id = :productid'
                ),
                array('productid' => $product->product_id)
            );

            $prices = array();
            foreach ($product->variants as $variant) {
                $prices[] = $variant->price;
                $promo_price = $variant->price;
                $temp_price = $variant->price;

                if (! empty($promo_products)) {
                    $promo_price = $variant->price;
                    $arr_promo = array();
                    foreach ($promo_products as $promo_filter) {
                        if ($promo_filter->rule_type == 'product_discount_by_percentage' || $promo_filter->rule_type == 'cart_discount_by_percentage') {
                            $discount = $promo_filter->discount_value * $variant->price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $promo_price = $promo_price - $discount;
                        } elseif ($promo_filter->rule_type == 'product_discount_by_value' || $promo_filter->rule_type == 'cart_discount_by_value') {
                            $discount = $promo_filter->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $promo_price = $promo_price - $discount;
                        } elseif ($promo_filter->rule_type == 'new_product_price') {
                            $new_price = $promo_filter->discount_value;
                            $discount = $variant->price - $new_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $promo_price = $promo_price - $discount;
                        }
                        $arr_promo[] = $promo_filter->promotion_id;
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
            if (! empty($promo_products)) {
                foreach ($promo_products as $promo_filter) {
                    if ($promo_filter->rule_type == 'product_discount_by_percentage' || $promo_filter->rule_type == 'cart_discount_by_percentage') {
                        $discount = $promo_filter->discount_value * $product->min_price;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $min_promo_price = $min_promo_price - $discount;
                    } elseif ($promo_filter->rule_type == 'product_discount_by_value' || $promo_filter->rule_type == 'cart_discount_by_value') {
                        $discount = $promo_filter->discount_value;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $min_promo_price = $min_promo_price - $discount;
                    } elseif ($promo_filter->rule_type == 'new_product_price') {
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

            if (! empty($coupons)) {
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

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Search page
     *
     * @param string    `keyword`        (optional) - The keyword, could be: upc code, product name, short or long description
     * @param string    `sort_by`        (optional)
     * @param string    `new`            (optional) - Fill with 1 to filter for new product only (new product page)
     * @param string    `take`           (optional)
     * @param string    `skip`           (optional)
     * @param string    `sort_mode`      (optional)
     *
     * @return \Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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

            // Filter product by name pattern
            OrbitInput::get(
                'keyword',
                function ($name) use ($products) {
                    $products->where(
                        function ($q) use ($name) {
                            $q->where('products.product_name', 'like', "%$name%")
                                ->orWhere('products.upc_code', 'like', "%$name%")
                                ->orWhere('products.product_code', 'like', "%$name%")
                                ->orWhere('products.short_description', 'like', "%$name%")
                                ->orWhere('products.long_description', 'like', "%$name%")
                                ->orWhere('products.short_description', 'like', "%$name%");
                        }
                    );
                }
            );

            // Filter by new product
            OrbitInput::get(
                'new',
                function ($name) use ($products) {
                    if (! empty($name)) {
                        $products->where(
                            function ($q) use ($name) {
                                $q->where('new_from', '<=', Carbon::now())->where('new_until', '>=', Carbon::now());
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

            $next_skip = $skip + $take;

            $totalRec = $_products->count();
            $listOfRec = $products->take($take)->skip($skip)->get();

            $no_more = FALSE;
            if($next_skip >= $totalRec) {
                $next_skip = $totalRec;
                $no_more = TRUE;
            }

            // $load_more = 'no';
            // OrbitInput::get(
            //     'load_more',
            //     function ($_loadmore) use (&$load_more) {
            //         if ($_loadmore == 'yes') {
            //             $load_more = $_loadmore;
            //         }
            //     }
            // );

            $cartitems = $this->getCartForToolbar();

            $promotions = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid FROM ' . DB::getTablePrefix() . 'promotions p
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
                
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );

            $product_on_promo = array();
            foreach ($promotions as $promotion) {
                $product_on_promo[] = $promotion->product_id;
            }

            // unused function: moved to getPromotionList
            OrbitInput::get(
                'promo',
                function ($name) use ($products, $product_on_promo) {
                    if (! empty($name)) {
                        if (! empty($product_on_promo)) {
                            $products->whereIn('products.product_id', $product_on_promo);
                        } else {
                            $products->where('product_id', '-1');
                        }
                    }
                }
            );

            $couponstocatchs = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid FROM ' . DB::getTablePrefix() . 'promotions p
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
                
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );

            $coupons = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid FROM ' . DB::getTablePrefix() . 'promotions p
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
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id)
            );

            $product_on_coupon = array();
            foreach ($coupons as $coupon) {
                $product_on_coupon[] = $coupon->product_id;
            }

            // unused function: moved to getCouponList
            OrbitInput::get(
                'coupon',
                function ($name) use ($products, $product_on_coupon) {
                    if (! empty($name)) {
                        if (! empty($product_on_coupon)) {
                            $products->whereIn('products.product_id', $product_on_coupon);
                        } else {
                            $products->where('product_id', '-1');
                        }
                    }
                }
            );

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

            // should not be limited for new products - limit only when searching
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

            if (! empty(OrbitInput::get('new'))) {
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

            return View::make('mobile-ci.search', array('page_title'=>$pagetitle, 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo, 'no_more' => $no_more, 'next_skip' => $next_skip));

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

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Product list (this function is used when the family is clicked on catalogue page)
     *
     * @param string    `sort_by`        (optional)
     * @param string    `sort_mode`      (optional)
     * @param array     `families`       (optional)
     * @param integer   `family_id`      (optional)
     * @param integer   `family_level`   (optional)
     *
     * @return \Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
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
            $family_id = 0;
            OrbitInput::get('family_id', function ($_family_id) use (&$family_id) {
                $family_id = $_family_id;
            });
            $family_level = 0;
            OrbitInput::get('family_level', function ($_family_level) use (&$family_level) {
                $family_level = $_family_level;
            });
            $families = array();
            OrbitInput::get('families', function ($_families) use (&$families) {
                $families = $_families;
            });

            if (count($families) == 1) {
                \Session::put('f1', $family_id);
                \Session::forget('f2');
                \Session::forget('f3');
                \Session::forget('f4');
                \Session::forget('f5');
            } elseif (count($families) == 2) {
                \Session::put('f2', $family_id);
                \Session::forget('f3');
                \Session::forget('f4');
                \Session::forget('f5');
            } elseif (count($families) == 3) {
                \Session::put('f3', $family_id);
                \Session::forget('f4');
                \Session::forget('f5');
            } elseif (count($families) == 4) {
                \Session::put('f4', $family_id);
                \Session::forget('f5');
            } elseif (count($families) == 5) {
                \Session::put('f5', $family_id);
            }

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    // 'family_id' => $family_id,
                ),
                array(
                    'sort_by' => 'in:product_name,price',
                    // 'family_id' => 'orbit.exists.category',
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

            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.per_page');
            if ($perPage <= 0) {
                $perPage = 20;
            }

            $retailer = $this->getRetailerInfo();

            $nextfamily = $family_level + 1;

            $subfamilies = null;

            if(! empty($families)) {
                if ($nextfamily < 6) {
                    $subfamilies = Category::active()
                        ->where('merchant_id', $retailer->parent_id)->whereHas(
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
                }
            }

            $products = Product::from(DB::raw(DB::getTablePrefix() . 'products use index(primary)'))->with('variants')
            // $products = Product::with('variants')
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
            ->active();

            if(! empty($families)) {
                $products->where(
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
            }

            // Filter product by name pattern
            OrbitInput::get(
                'keyword',
                function ($name) use ($products) {
                    $products->where(
                        function ($q) use ($name) {
                            $q->where('products.product_name', 'like', "%$name%")
                                ->orWhere('products.upc_code', 'like', "%$name%")
                                ->orWhere('products.product_code', 'like', "%$name%")
                                ->orWhere('products.short_description', 'like', "%$name%")
                                ->orWhere('products.long_description', 'like', "%$name%")
                                ->orWhere('products.short_description', 'like', "%$name%");
                        }
                    );
                }
            );

            // Filter by new product
            OrbitInput::get(
                'new',
                function ($name) use ($products) {
                    if (! empty($name)) {
                        $products->where(
                            function ($q) use ($name) {
                                $q->where('new_from', '<=', Carbon::now())->where('new_until', '>=', Carbon::now());
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

            $next_skip = $skip + $take;

            $totalRec = $_products->count();
            $listOfRec = $products->take($take)->skip($skip)->get();

            $no_more = FALSE;
            if($next_skip >= $totalRec) {
                $next_skip = $totalRec;
                $no_more = TRUE;
            }

            $load_more = 'no';
            OrbitInput::get(
                'load_more',
                function ($_loadmore) use (&$load_more) {
                    if ($_loadmore == 'yes') {
                        $load_more = $_loadmore;
                    }
                }
            );

            // dd($listOfRec);
            $promotions = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid FROM ' . DB::getTablePrefix() . 'promotions p
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
                
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );

            $couponstocatchs = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid FROM ' . DB::getTablePrefix() . 'promotions p
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
                
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id)
            );

            $coupons = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid FROM ' . DB::getTablePrefix() . 'promotions p
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
                '
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
            $data->status = 1;
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;
            $data->family_id = $family_id;
            $data->family_level = $family_level;
            $data->no_more = $no_more;

            $cartitems = $this->getCartForToolbar();

            $activityfamily = Category::where('category_id', $family_id)->first();

            if(! empty($families)) {
                $activityCategoryNotes = sprintf('Category viewed: %s', $activityfamily->category_name);
                $activityCategory->setUser($user)
                    ->setActivityName('view_catalogue')
                    ->setActivityNameLong('View Catalogue ' . $activityfamily->category_name)
                    ->setObject($activityfamily)
                    ->setModuleName('Catalogue')
                    ->setNotes($activityCategoryNotes)
                    ->responseOK()
                    ->save();
            }

            return View::make('mobile-ci.product-list', array('retailer' => $retailer, 'data' => $data, 'subfamilies' => $subfamilies, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo, 'couponstocatchs' => $couponstocatchs, 'load_more' => $load_more, 'next_skip' => $next_skip));

        } catch (Exception $e) {
            $activityCategoryNotes = sprintf('Category viewed: %s', $family_id);
            $activityCategory->setUser($user)
                ->setActivityName('view_catalogue')
                ->setActivityNameLong('View Catalogue Failed')
                ->setObject(null)
                ->setModuleName('Catalogue')
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }

    }

    /**
     * GET - Product detail scan page
     *
     * @param integer    `upc_code`        (required) - The product ID
     *
     * @return \Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getProductScanView()
    {
        $user = null;
        $upc_code = 0;
        $activityProduct = Activity::mobileci()
            ->setActivityType('view');
        $product = null;
        try {
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();
            $upc_code = trim(OrbitInput::get('upc'));
            // $product_only = Product::where('upc_code', $upc_code)->active()->first();
            $cartitems = $this->getCartForToolbar();

            $product = Product::with('variants', 'attribute1', 'attribute2', 'attribute3', 'attribute4', 'attribute5')
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
            ->wherehas(
                'variants',
                function($query2) use ($upc_code) {
                    $query2->where('product_variants.upc', $upc_code);
                }
            )->active()->first();

            if (empty($product)) {
                // throw new Exception('Product id ' . $product_id . ' not found');
                return View::make('mobile-ci.404', array('page_title'=>Lang::get('mobileci.page_title.not_found'), 'retailer'=>$retailer, 'cartitems' => $cartitems));
            }

            $selected_variant = \ProductVariant::active()->where('upc', $upc_code)->first();

            // $promo_products = DB::select(
            //     DB::raw(
            //         'SELECT * FROM ' . DB::getTablePrefix() . 'promotions p
            //     inner join ' . DB::getTablePrefix() . 'promotion_rules pr on p.promotion_id = pr.promotion_id and p.status = "active" and ((p.begin_date <= "' . Carbon::now() . '"  and p.end_date >= "' . Carbon::now() . '") or (p.begin_date <= "' . Carbon::now() . '" AND p.is_permanent = "Y")) and p.is_coupon = "N" AND p.merchant_id = :merchantid
            //     inner join ' . DB::getTablePrefix() . 'promotion_retailer prr on prr.promotion_id = p.promotion_id AND prr.retailer_id = :retailerid
            //     inner join ' . DB::getTablePrefix() . 'products prod on
            //     (
            //         (pr.discount_object_type="product" AND pr.discount_object_id1 = prod.product_id)
            //         OR
            //         (
            //             (pr.discount_object_type="family") AND
            //             ((pr.discount_object_id1 IS NULL) OR (pr.discount_object_id1=prod.category_id1)) AND
            //             ((pr.discount_object_id2 IS NULL) OR (pr.discount_object_id2=prod.category_id2)) AND
            //             ((pr.discount_object_id3 IS NULL) OR (pr.discount_object_id3=prod.category_id3)) AND
            //             ((pr.discount_object_id4 IS NULL) OR (pr.discount_object_id4=prod.category_id4)) AND
            //             ((pr.discount_object_id5 IS NULL) OR (pr.discount_object_id5=prod.category_id5))
            //         )
            //     )
            //     WHERE prod.product_id = :productid'
            //     ),
            //     array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product->product_id)
            // );
            
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
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product->product_id)
            );
            
            $couponstocatchs = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid FROM ' . DB::getTablePrefix() . 'promotions p
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
                WHERE ((prod.product_id = :productid AND pr.is_all_product_rule = "N") OR pr.is_all_product_rule = "Y") AND (prr.retailer_id = :retailerid OR (p.is_all_retailer = "Y" AND p.merchant_id = :merchantid))
                
                '
                ),
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'productid' => $product->product_id)
            );

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
                array('merchantid' => $retailer->parent_id, 'retailerid' => $retailer->merchant_id, 'userid' => $user->user_id, 'productid' => $product->product_id)
            );

            $attributes = DB::select(
                DB::raw(
                    'SELECT v.upc, v.sku, v.product_variant_id, av1.value as value1, av1.product_attribute_value_id as attr_val_id1, av2.product_attribute_value_id as attr_val_id2, av3.product_attribute_value_id as attr_val_id3, av4.product_attribute_value_id as attr_val_id4, av5.product_attribute_value_id as attr_val_id5, av2.value as value2, av3.value as value3, av4.value as value4, av5.value as value5, v.price, pa1.product_attribute_name as attr1, pa2.product_attribute_name as attr2, pa3.product_attribute_name as attr3, pa4.product_attribute_name as attr4, pa5.product_attribute_name as attr5 FROM ' . DB::getTablePrefix() . 'product_variants v
                inner join ' . DB::getTablePrefix() . 'products p on p.product_id = v.product_id AND p.status = "active" AND v.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av1 on av1.product_attribute_value_id = v.product_attribute_value_id1 AND av1.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av2 on av2.product_attribute_value_id = v.product_attribute_value_id2 AND av2.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av3 on av3.product_attribute_value_id = v.product_attribute_value_id3 AND av3.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av4 on av4.product_attribute_value_id = v.product_attribute_value_id4 AND av4.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attribute_values as av5 on av5.product_attribute_value_id = v.product_attribute_value_id5 AND av5.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa1 on pa1.product_attribute_id = av1.product_attribute_id AND pa1.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa2 on pa2.product_attribute_id = av2.product_attribute_id AND pa2.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa3 on pa3.product_attribute_id = av3.product_attribute_id AND pa3.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa4 on pa4.product_attribute_id = av4.product_attribute_id AND pa4.status = "active"
                left join ' . DB::getTablePrefix() . 'product_attributes as pa5 on pa5.product_attribute_id = av5.product_attribute_id AND pa5.status = "active"
                WHERE p.product_id = :productid'
                ),
                array('productid' => $product->product_id)
            );

            $prices = array();
            foreach ($product->variants as $variant) {
                $prices[] = $variant->price;
                $promo_price = $variant->price;
                $temp_price = $variant->price;

                if (! empty($promo_products)) {
                    $promo_price = $variant->price;
                    $arr_promo = array();
                    foreach ($promo_products as $promo_filter) {
                        if ($promo_filter->rule_type == 'product_discount_by_percentage' || $promo_filter->rule_type == 'cart_discount_by_percentage') {
                            $discount = $promo_filter->discount_value * $variant->price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $promo_price = $promo_price - $discount;
                        } elseif ($promo_filter->rule_type == 'product_discount_by_value' || $promo_filter->rule_type == 'cart_discount_by_value') {
                            $discount = $promo_filter->discount_value;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $promo_price = $promo_price - $discount;
                        } elseif ($promo_filter->rule_type == 'new_product_price') {
                            $new_price = $promo_filter->discount_value;
                            $discount = $variant->price - $new_price;
                            if ($temp_price < $discount) {
                                $discount = $temp_price;
                            }
                            $promo_price = $promo_price - $discount;
                        }
                        $arr_promo[] = $promo_filter->promotion_id;
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
            if (! empty($promo_products)) {
                foreach ($promo_products as $promo_filter) {
                    if ($promo_filter->rule_type == 'product_discount_by_percentage' || $promo_filter->rule_type == 'cart_discount_by_percentage') {
                        $discount = $promo_filter->discount_value * $product->min_price;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $min_promo_price = $min_promo_price - $discount;
                    } elseif ($promo_filter->rule_type == 'product_discount_by_value' || $promo_filter->rule_type == 'cart_discount_by_value') {
                        $discount = $promo_filter->discount_value;
                        if ($temp_price < $discount) {
                            $discount = $temp_price;
                        }
                        $min_promo_price = $min_promo_price - $discount;
                    } elseif ($promo_filter->rule_type == 'new_product_price') {
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

            if (! empty($coupons)) {
                $product->on_coupons = true;
            } else {
                $product->on_coupons = false;
            }

            $activityProductNotes = sprintf('Product viewed from scan: %s', $product->product_name);
            $activityProduct->setUser($user)
                ->setActivityName('view_product_scan')
                ->setActivityNameLong('View Product Scan')
                ->setObject($product)
                ->setProduct($product)
                ->setModuleName('Product')
                ->setNotes($activityProductNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.product', array('page_title' => strtoupper($product->product_name), 'retailer' => $retailer, 'product' => $product, 'cartitems' => $cartitems, 'promotions' => $promo_products, 'attributes' => $attributes, 'couponstocatchs' => $couponstocatchs, 'coupons' => $coupons, 'selected_variant' => $selected_variant));

        } catch (Exception $e) {
            $activityProductNotes = sprintf('Product viewed from scan: %s', $upc_code);
            $activityProduct->setUser($user)
                ->setActivityName('view_product_scan')
                ->setActivityNameLong('View Product Not Found')
                ->setObject(null)
                ->setProduct($product)
                ->setModuleName('Product')
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }
}
