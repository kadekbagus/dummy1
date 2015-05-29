<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Product;
use Coupon;

class CouponPrinterController extends DataPrinterController
{
    public function getCouponPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $now = date('Y-m-d H:i:s');

        $coupons = Coupon::with('couponrule')
            ->excludeDeleted('promotions')
            ->allowedForViewOnly($user)
            ->select(DB::raw($prefix . "promotions.*,
                    CASE rule_type
                        WHEN 'cart_discount_by_percentage' THEN 'percentage'
                        WHEN 'product_discount_by_percentage' THEN 'percentage'
                        WHEN 'cart_discount_by_value' THEN 'value'
                        WHEN 'product_discount_by_value' THEN 'value'
                        ELSE NULL
                    END AS 'display_discount_type',
                    CASE rule_type
                        WHEN 'cart_discount_by_percentage' THEN discount_value * 100
                        WHEN 'product_discount_by_percentage' THEN discount_value * 100
                        ELSE discount_value
                    END AS 'display_discount_value'
                    "), 
                    DB::raw("GROUP_CONCAT(`{$prefix}merchants`.`name` SEPARATOR ', ') as retailer_list"),
                    DB::raw('cat1.category_name as family_name1'),
                    DB::raw('cat2.category_name as family_name2'),
                    DB::raw('cat3.category_name as family_name3'),
                    DB::raw('cat4.category_name as family_name4'),
                    DB::raw('cat5.category_name as family_name5'),
                   "promotion_rules.discount_value as discount_value",
                   "promotion_rules.discount_object_type as discount_object_type",
                   "products.product_name as product_name"
            )
            ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id')
            ->leftJoin('promotion_retailer_redeem', 'promotions.promotion_id', '=', 'promotion_retailer_redeem.promotion_id')
            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer_redeem.retailer_id')
            ->leftJoin(DB::raw("{$prefix}products"), function($join) {
                $join->on('products.product_id', '=', 'promotion_rules.discount_object_id1');
                $join->on('promotion_rules.discount_object_type', '=', DB::raw("'product'"));
            })
            ->leftJoin(DB::raw("{$prefix}categories cat1"), function($join) {
                $join->on(DB::raw('cat1.category_id'), '=', 'promotion_rules.discount_object_id1');
                $join->on('promotion_rules.discount_object_type', '=', DB::raw("'family'"));
            })
            ->leftJoin(DB::raw("{$prefix}categories cat2"), function($join) {
                $join->on(DB::raw('cat2.category_id'), '=', 'promotion_rules.discount_object_id2');
                $join->on('promotion_rules.discount_object_type', '=', DB::raw("'family'"));
            }) 
            ->leftJoin(DB::raw("{$prefix}categories cat3"), function($join) {
                $join->on(DB::raw('cat3.category_id'), '=', 'promotion_rules.discount_object_id3');
                $join->on('promotion_rules.discount_object_type', '=', DB::raw("'family'"));
            })
            ->leftJoin(DB::raw("{$prefix}categories cat4"), function($join) {
                $join->on(DB::raw('cat4.category_id'), '=', 'promotion_rules.discount_object_id4');
                $join->on('promotion_rules.discount_object_type', '=', DB::raw("'family'"));
            })
            ->leftJoin(DB::raw("{$prefix}categories cat5"), function($join) {
                $join->on(DB::raw('cat5.category_id'), '=', 'promotion_rules.discount_object_id5');
                $join->on('promotion_rules.discount_object_type', '=', DB::raw("'family'"));
            })
            ->groupBy('promotions.promotion_id');

        // Filter coupon by Ids
        OrbitInput::get('promotion_id', function($promotionIds) use ($coupons)
        {
            $coupons->whereIn('promotions.promotion_id', $promotionIds);
        });

        // Filter coupon by merchant Ids
        OrbitInput::get('merchant_id', function ($merchantIds) use ($coupons) {
            $coupons->whereIn('promotions.merchant_id', $merchantIds);
        });

        // Filter coupon by promotion name
        OrbitInput::get('promotion_name', function($promotionName) use ($coupons)
        {
            $coupons->whereIn('promotions.promotion_name', $promotionName);
        });

        // Filter coupon by matching promotion name pattern
        OrbitInput::get('promotion_name_like', function($promotionName) use ($coupons)
        {
            $coupons->where('promotions.promotion_name', 'like', "%$promotionName%");
        });

        // Filter coupon by promotion type
        OrbitInput::get('promotion_type', function($promotionTypes) use ($coupons)
        {
            $coupons->whereIn('promotions.promotion_type', $promotionTypes);
        });

        // Filter coupon by description
        OrbitInput::get('description', function($description) use ($coupons)
        {
            $coupons->whereIn('promotions.description', $description);
        });

        // Filter coupon by matching description pattern
        OrbitInput::get('description_like', function($description) use ($coupons)
        {
            $coupons->where('promotions.description', 'like', "%$description%");
        });

        // Filter coupon by begin date
        OrbitInput::get('begin_date', function($beginDate) use ($coupons)
        {
            $coupons->where('promotions.begin_date', '<=', $beginDate);
        });

        // Filter coupon by end date
        OrbitInput::get('end_date', function($endDate) use ($coupons)
        {
            $coupons->where('promotions.end_date', '>=', $endDate);
        });

        // Filter coupon by end_date for begin
        OrbitInput::get('expiration_begin_date', function($begindate) use ($coupons)
        {
            $coupons->where('promotions.end_date', '>=', $begindate)
                    ->where('promotions.is_permanent', 'N');
        });

        // Filter coupon by end_date for end
        OrbitInput::get('expiration_end_date', function($enddate) use ($coupons)
        {
            $coupons->where('promotions.end_date', '<=', $enddate)
                    ->where('promotions.is_permanent', 'N');
        });

        // Filter coupon by is permanent
        OrbitInput::get('is_permanent', function ($isPermanent) use ($coupons) {
            $coupons->whereIn('promotions.is_permanent', $isPermanent);
        });

        // Filter coupon by coupon notification
        OrbitInput::get('coupon_notification', function ($couponNotification) use ($coupons) {
            $coupons->whereIn('promotions.coupon_notification', $couponNotification);
        });

        // Filter coupon by status
        OrbitInput::get('status', function ($statuses) use ($coupons) {
            $coupons->whereIn('promotions.status', $statuses);
        });

        // Filter coupon rule by rule type
        OrbitInput::get('rule_type', function ($ruleTypes) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($ruleTypes) {
                $q->whereIn('rule_type', $ruleTypes);
            });
        });

         // Filter coupon rule by rule object type
        OrbitInput::get('rule_object_type', function ($ruleObjectTypes) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($ruleObjectTypes) {
                $q->whereIn('rule_object_type', $ruleObjectTypes);
            });
        });

        // Filter coupon rule by rule object id1
        OrbitInput::get('rule_object_id1', function ($ruleObjectId1s) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($ruleObjectId1s) {
                $q->whereIn('rule_object_id1', $ruleObjectId1s);
            });
        });

        // Filter coupon rule by rule object id2
        OrbitInput::get('rule_object_id2', function ($ruleObjectId2s) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($ruleObjectId2s) {
                $q->whereIn('rule_object_id2', $ruleObjectId2s);
            });
        });

        // Filter coupon rule by rule object id3
        OrbitInput::get('rule_object_id3', function ($ruleObjectId3s) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($ruleObjectId3s) {
                $q->whereIn('rule_object_id3', $ruleObjectId3s);
            });
        });

        // Filter coupon rule by rule object id4
        OrbitInput::get('rule_object_id4', function ($ruleObjectId4s) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($ruleObjectId4s) {
                $q->whereIn('rule_object_id4', $ruleObjectId4s);
            });
        });

        // Filter coupon rule by rule object id5
        OrbitInput::get('rule_object_id5', function ($ruleObjectId5s) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($ruleObjectId5s) {
                $q->whereIn('rule_object_id5', $ruleObjectId5s);
            });
        });

        // Filter coupon rule by discount object type
        OrbitInput::get('discount_object_type', function ($discountObjectTypes) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($discountObjectTypes) {
                $q->whereIn('discount_object_type', $discountObjectTypes);
            });
        });

        // Filter coupon rule by discount object id1
        OrbitInput::get('discount_object_id1', function ($discountObjectId1s) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($discountObjectId1s) {
                $q->whereIn('discount_object_id1', $discountObjectId1s);
            });
        });

        // Filter coupon rule by discount object id2
        OrbitInput::get('discount_object_id2', function ($discountObjectId2s) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($discountObjectId2s) {
                $q->whereIn('discount_object_id2', $discountObjectId2s);
            });
        });

        // Filter coupon rule by discount object id3
        OrbitInput::get('discount_object_id3', function ($discountObjectId3s) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($discountObjectId3s) {
                $q->whereIn('discount_object_id3', $discountObjectId3s);
            });
        });

        // Filter coupon rule by discount object id4
        OrbitInput::get('discount_object_id4', function ($discountObjectId4s) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($discountObjectId4s) {
                $q->whereIn('discount_object_id4', $discountObjectId4s);
            });
        });

        // Filter coupon rule by discount object id5
        OrbitInput::get('discount_object_id5', function ($discountObjectId5s) use ($coupons) {
            $coupons->whereHas('couponrule', function($q) use ($discountObjectId5s) {
                $q->whereIn('discount_object_id5', $discountObjectId5s);
            });
        });

        // Filter coupon rule by matching discount object name pattern (product or family link)
        OrbitInput::get('discount_object_name_like', function ($discount_object_name) use ($coupons) {
            $coupons->whereHas('couponrule', function ($q) use ($discount_object_name) {
                $q->where(function($q) use ($discount_object_name) {
                    $q->whereHas('discountproduct', function ($q) use ($discount_object_name) {
                        $q->where('discount_object_type', 'product')
                          ->where('product_name', 'like', "%$discount_object_name%");
                    });
                    $q->orWhereHas('discountcategory1', function ($q) use ($discount_object_name) {
                        $q->where('discount_object_type', 'family')
                          ->where('category_name', 'like', "%$discount_object_name%");
                    });
                    $q->orWhereHas('discountcategory2', function ($q) use ($discount_object_name) {
                        $q->where('discount_object_type', 'family')
                          ->where('category_name', 'like', "%$discount_object_name%");
                    });
                    $q->orWhereHas('discountcategory3', function ($q) use ($discount_object_name) {
                        $q->where('discount_object_type', 'family')
                          ->where('category_name', 'like', "%$discount_object_name%");
                    });
                    $q->orWhereHas('discountcategory4', function ($q) use ($discount_object_name) {
                        $q->where('discount_object_type', 'family')
                          ->where('category_name', 'like', "%$discount_object_name%");
                    });
                    $q->orWhereHas('discountcategory5', function ($q) use ($discount_object_name) {
                        $q->where('discount_object_type', 'family')
                          ->where('category_name', 'like', "%$discount_object_name%");
                    });
                });
            });
        });

        // Filter coupon by issue retailer id
        OrbitInput::get('issue_retailer_id', function ($issueRetailerIds) use ($coupons) {
            $coupons->whereHas('issueretailers', function($q) use ($issueRetailerIds) {
                $q->whereIn('retailer_id', $issueRetailerIds);
            });
        });

        // Filter coupon by redeem retailer id
        OrbitInput::get('redeem_retailer_id', function ($redeemRetailerIds) use ($coupons) {
            $coupons->whereHas('redeemretailers', function($q) use ($redeemRetailerIds) {
                $q->whereIn('retailer_id', $redeemRetailerIds);
            });
        });

        // Add new relation based on request
        OrbitInput::get('with', function ($with) use ($coupons) {
            $with = (array) $with;

            foreach ($with as $relation) {
                if ($relation === 'retailers') {
                    $coupons->with('issueretailers', 'redeemretailers');
                } elseif ($relation === 'product') {
                    $coupons->with('couponrule.ruleproduct', 'couponrule.discountproduct');
                } elseif ($relation === 'family') {
                    $coupons->with('couponrule.rulecategory1', 'couponrule.rulecategory2', 'couponrule.rulecategory3', 'couponrule.rulecategory4', 'couponrule.rulecategory5', 'couponrule.discountcategory1', 'couponrule.discountcategory2', 'couponrule.discountcategory3', 'couponrule.discountcategory4', 'couponrule.discountcategory5');
                }
            }
        });

        // Clone the query builder which still does not include the take,
        // skip, and order by
        $_coupons = clone $coupons;

        // Default sort by
        $sortBy = 'promotions.promotion_name';
        // Default sort mode
        $sortMode = 'asc';

        OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
        {
            // Map the sortby request to the real column name
            $sortByMapping = array(
                'registered_date'          => 'promotions.created_at',
                'promotion_name'           => 'promotions.promotion_name',
                'promotion_type'           => 'promotions.promotion_type',
                'description'              => 'promotions.description',
                'begin_date'               => 'promotions.begin_date',
                'end_date'                 => 'promotions.end_date',
                'is_permanent'             => 'promotions.is_permanent',
                'status'                   => 'promotions.status',
                'rule_type'                => 'rule_type',
                'display_discount_value'   => 'display_discount_value' // only to avoid error 'Undefined index'
            );

            $sortBy = $sortByMapping[$_sortBy];
        });

        OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
        {
            if (strtolower($_sortMode) !== 'asc') {
                $sortMode = 'desc';
            }
        });

        if (trim(OrbitInput::get('sortby')) === 'display_discount_value') {
            $coupons->orderBy('display_discount_type', $sortMode);
            $coupons->orderBy('display_discount_value', $sortMode);
        } else {
            $coupons->orderBy($sortBy, $sortMode);
        }

        $totalRec = RecordCounter::create($_coupons)->count();

        $this->prepareUnbufferedQuery();

        $sql = $coupons->toSql();
        $binds = $coupons->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);

        switch ($mode) {
            case 'csv':
                $filename = 'coupon-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Coupon List', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Coupon', $totalRec, '', '', '', '','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Name', 'Expiration Date', 'Redeem Retailer', 'Discount Type', 'Discount Value', 'Product or Family Link', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $expiration_date = $this->printExpirationDate($row);
                    $discount_type = $this->printDiscountType($row);
                    $productfamilylink = $this->printProductFamilyLink($row);

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\", %s,\"%s\",\"%s\"\n", '', $this->printUtf8($row->promotion_name), $expiration_date, $this->printUtf8($row->retailer_list), $discount_type, $row->discount_value, $productfamilylink, $row->status);
                }
                break;

            case 'print':
            default:
                $me = $this;
                $pageTitle = 'Coupon';
                require app_path() . '/views/printer/list-coupon-view.php';
        }
    }

    public function getRetailerInfo()
    {
        try {
            $retailer_id = Config::get('orbit.shop.id');
            $retailer = \Retailer::with('parent')->where('merchant_id', $retailer_id)->first();

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
     * Print discount type friendly name.
     *
     * @param $promotion $promotion
     * @return string
     */
    public function printDiscountType($promotion)
    {
        switch ($promotion->promotion_type) {
            case 'cart':
                $result = 'Cart Discount By ' . ucfirst($promotion->display_discount_type);
                break;

            case 'product':
            default:
                $result = 'Product Discount By ' . ucfirst($promotion->display_discount_type);
        }

        return $result;
    }


    /**
     * Print expiration date type friendly name.
     *
     * @param $promotion $promotion
     * @return string
     */
    public function printExpirationDate($promotion)
    {
        switch ($promotion->is_permanent) {
            case 'Y':
                $result = 'Permanent';
                break;

            case 'N':
            default:
                if($promotion->end_date==NULL | empty($promotion->end_date)){
                    $result = "";
                } else {
                    $date = $promotion->end_date;
                    $date = explode(' ',$date);
                    $time = strtotime($date[0]);
                    $newformat = date('d F Y',$time);
                    $result = $newformat;
                }
        }

        return $result;
    }


    /**
     * Print expiration date type friendly name.
     *
     * @param $promotion $promotion
     * @return string
     */
    public function printDiscountValue($promotion)
    {
        $retailer = $this->getRetailerInfo();
        $currency = strtolower($retailer->parent->currency);
        switch ($promotion->display_discount_type) {
            case 'percentage':
                $discount =  $promotion->discount_value*100;
                $result = $discount."%";
                break;
                
            default:
                if($currency=='usd'){
                    $result = number_format($promotion->discount_value, 2);
                } else {
                    $result = number_format($promotion->discount_value);
                }
        }

        return $result;
    }


    /**
     * Print product or family link friendly name.
     *
     * @param $promotion $promotion
     * @return string
     */
    public function printProductFamilyLink($promotion)
    {
        switch($promotion->discount_object_type){
            case 'product':
                $result = $promotion->product_name;
                break;
            case 'family':
                $families = [];
                $families[] = $promotion->family_name1;
                $families[] = $promotion->family_name2;
                $families[] = $promotion->family_name3;
                $families[] = $promotion->family_name4;
                $families[] = $promotion->family_name5;

                // Remove empty array
                $families = array_filter($families);

                // Join with comma separator
                $families = implode(', ', $families);

                $result = $families;
                break;
            default:
                $result = ''; 
        }
        
        return $result;
    }

    /**
     * output utf8.
     *
     * @param string $input
     * @return string
     */
    public function printUtf8($input)
    {
        return utf8_encode($input);
    }
}