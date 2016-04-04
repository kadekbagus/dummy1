<?php namespace MobileCI;

/**
 * An API controller for managing Mobile CI.
 */
use Activity;
use Carbon\Carbon as Carbon;
use Category;
use Config;
use DB;
use EventModel;
use EventProduct;
use Exception;
use IssuedCoupon;
use Lang;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use Product;
use stdclass;
use Validator;
use View;

class CategoryController extends MobileCIAPIController
{

    /**
     * GET - Catalogue page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Illuminate\View\View
     */
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
            $families = Category::whereHas('product1', function($q) use($retailer) {
                    $q->where('products.status', 'active');
                    $q->where(function($q2) use ($retailer) {
                        $q2->where(function($q3) use($retailer) {
                            $q3->where('is_all_retailer', 'Y');
                            $q3->where('merchant_id', $retailer->parent->merchant_id);
                        });
                        $q2->orWhere(function($q3) use ($retailer) {
                            $q3->where('is_all_retailer', 'N');
                            $q3->whereHas('retailers', function($q4) use($retailer) {
                                $q4->where('product_retailer.retailer_id', $retailer->merchant_id);
                            });
                        });
                    });
                })->where('merchant_id', $retailer->parent_id)->active()->orderBy('categories.category_name', 'asc')->get();

            $cartitems = $this->getCartForToolbar();

            $family1 = \Session::get('f1');
            $family2 = \Session::get('f2');
            $family3 = \Session::get('f3');
            $family4 = \Session::get('f4');
            $family5 = \Session::get('f5');

            if (! empty($family1) || ! empty($family2) || ! empty($family3) || ! empty($family4) || ! empty($family5)) {
                $hasFamily = 'yes';
            } else {
                $hasFamily = 'no';
            }

            $array_of_families = array();
            if (! empty($family1)) {
                $array_of_families_lvl1[] = $family1;
            }
            if (! empty($family2)) {
                $array_of_families_lvl2[] = $family1;
                $array_of_families_lvl2[] = $family2;
            }
            if (! empty($family3)) {
                $array_of_families_lvl3[] = $family1;
                $array_of_families_lvl3[] = $family2;
                $array_of_families_lvl3[] = $family3;
            }
            if (! empty($family4)) {
                $array_of_families_lvl4[] = $family1;
                $array_of_families_lvl4[] = $family2;
                $array_of_families_lvl4[] = $family3;
                $array_of_families_lvl4[] = $family4;
            }
            if (! empty($family5)) {
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

            if ($hasFamily == 'yes') {
                if (! empty($family1)) {
                    $lvl1 = $this->getProductListCatalogue($array_of_families_lvl1, 1, $family1, '');
                    $activityfamilyid = $family1;
                }
                if (! empty($family2)) {
                    $lvl2 = $this->getProductListCatalogue($array_of_families_lvl2, 2, $family2, '');
                    $activityfamilyid = $family2;
                }
                if (! empty($family3)) {
                    $lvl3 = $this->getProductListCatalogue($array_of_families_lvl3, 3, $family3, '');
                    $activityfamilyid = $family3;
                }
                if (! empty($family4)) {
                    $lvl4 = $this->getProductListCatalogue($array_of_families_lvl4, 4, $family4, '');
                    $activityfamilyid = $family4;
                }
                if (! empty($family5)) {
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
                ->setActivityNameLong('View Catalogue ' . $cat_name)
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
                ->setActivityNameLong('View Catalogue Failed')
                ->setObject(null)
                ->setModuleName('Catalogue')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Category page
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
    public function getCategory()
    {
        $user = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
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
                                $q->where(function($q2) {
                                    $q2->where('new_from', '<=', Carbon::now())->where('new_until', '>=', Carbon::now());
                                });
                                $q->orWhere(function($q2) {
                                    $q2->where('new_from', '<=', Carbon::now())->where('new_from', '<>', '0000-00-00 00:00:00')->where('new_until', '0000-00-00 00:00:00');
                                });
                            }
                        );
                    }
                }
            );

            $title = array();
            // Filter by category/family

            $title[] = OrbitInput::get(
                'f1',
                function ($name) use ($products) {
                    if (! empty($name)) {
                        $products->where('category_id1', $name);
                        $cat = Category::where('category_id', $name)->first()->category_name;

                        return $cat;
                    }
                }
            );

            $title[] = OrbitInput::get(
                'f2',
                function ($name) use ($products) {
                    if (! empty($name)) {
                        $products->where('category_id2', $name);
                        $cat = Category::where('category_id', $name)->first()->category_name;

                        return $cat;
                    }
                }
            );

            $title[] = OrbitInput::get(
                'f3',
                function ($name) use ($products) {
                    if (! empty($name)) {
                        $products->where('category_id3', $name);
                        $cat = Category::where('category_id', $name)->first()->category_name;

                        return $cat;
                    }
                }
            );

            $title[] = OrbitInput::get(
                'f4',
                function ($name) use ($products) {
                    if (! empty($name)) {
                        $products->where('category_id4', $name);
                        $cat = Category::where('category_id', $name)->first()->category_name;

                        return $cat;
                    }
                }
            );

            $title[] = OrbitInput::get(
                'f5',
                function ($name) use ($products) {
                    if (! empty($name)) {
                        $products->where('category_id5', $name);
                        $cat = Category::where('category_id', $name)->first()->category_name;

                        return $cat;
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

            $cartitems = $this->getCartForToolbar();

            $promotions = DB::select(
                DB::raw(
                    'SELECT *, p.promotion_id as promoid, prod.product_id as prodid FROM ' . DB::getTablePrefix() . 'promotions p
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
                    'SELECT *, p.promotion_id as promoid FROM ' . DB::getTablePrefix() . 'promotions p
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
            // $search_limit = Config::get('orbit.shop.search_limit');
            // if ($totalRec>$search_limit) {
            //     $data = new stdclass();
            //     $data->status = 0;
            // } else {
                $data = new stdclass();
                $data->status = 1;
                $data->total_records = $totalRec;
                $data->returned_records = count($listOfRec);
                $data->records = $listOfRec;
            // }

            if (! empty($title)) {
                $ttl = array_filter(
                    $title,
                    function ($v) {
                        return ! empty($v);
                    }
                );
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

            return View::make('mobile-ci.category', array('page_title'=>$pagetitle, 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'promo_products' => $product_on_promo, 'next_skip' => $next_skip, 'no_more' => $no_more));

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

            return $this->redirectIfNotLoggedIn($e);
        }
    }

    /**
     * GET - Event detail page
     *
     * @param string    `eventid`        (required) - The Event ID
     * @param string    `sort_by`        (optional)
     * @param string    `sort_mode`      (optional)
     *
     * @return \Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */    
    public function getEventDetailView() {
        $user = null;
        $event_id = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('view');
        try {
            // Require authentication
            $this->registerCustomValidation();
            $user = $this->getLoggedInUser();

            $retailer = $this->getRetailerInfo();

            $cartitems = $this->getCartForToolbar();

            $sort_by = OrbitInput::get('sort_by');

            $pagetitle = Lang::get('mobileci.page_title.searching');

            $event_id = OrbitInput::get('eventid');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'event_id' => $event_id,
                ),
                array(
                    'sort_by' => 'in:product_name,price',
                    'event_id' => 'required|orbit.exists.event',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                    'orbit.exists.event' => Lang::get('mobileci.exists.event')
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                // OrbitShopAPI::throwInvalidArgument($errorMessage);
                $page_title = 'ERROR';
                return View::make('mobile-ci.general-errors', compact('errorMessage', 'retailer', 'cartitems', 'page_title'));
            }

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 300;
            }

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

                    WHERE p.merchant_id = :merchantid AND CASE WHEN p.is_all_retailer = "N" THEN prr.retailer_id = :retailerid ELSE TRUE END'
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

                WHERE prr.retailer_id = :retailerid OR (p.is_all_retailer = "Y" AND p.merchant_id = :merchantid)
                
                '
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
                    'SELECT *, p.promotion_id as promoid FROM ' . DB::getTablePrefix() . 'promotions p
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

            $event = EventModel::active()
                ->where('event_id', $event_id)
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

            if (empty((array) $event)) {
                return View::make('mobile-ci.general-errors');
            }
            
            if (empty($event->image)) {
                $event->image = 'mobile-ci/images/default_product.png';
            }        
            // dd($event);
            if (! empty($event)) {
                if ($event->is_all_product === 'N') {
                    $product_on_event = EventProduct::where('event_id', $event->event_id)->lists('product_id');
                    $products->whereIn('products.product_id', $product_on_event);
                }
            }

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

            if (! empty($coupons)) {
                $pagetitle = Lang::get('mobileci.page_title.event_single') . ': ' . $event->event_name;
            }
            $activityPageNotes = sprintf('Page viewed: Coupon Detail, Issued Coupon Id: %s', $event_id);
            $activityPage->setUser($user)
                ->setActivityName('view_page_coupon_detail')
                ->setActivityNameLong('View (Coupon Detail Page)')
                ->setObject(null)
                ->setModuleName('Catalogue')
                ->setNotes($activityPageNotes)
                ->responseOK()
                ->save();

            return View::make('mobile-ci.events', array('page_title'=>$pagetitle, 'retailer' => $retailer, 'data' => $data, 'cartitems' => $cartitems, 'promotions' => $promotions, 'coupons' => $coupons, 'event' => $event, 'no_more' => $no_more, 'next_skip' => $next_skip));

        } catch (Exception $e) {
            $activityPageNotes = sprintf('Failed to view Page: Coupon Detail, Issued Coupon Id: %s', $event_id);
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
