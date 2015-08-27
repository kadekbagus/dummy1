<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Text as OrbitText;
use Product;
use Promotion;

class PromotionPrinterController extends DataPrinterController
{
    public function getPromotionPrintView()
    {
        $this->preparePDO();
        $prefix = DB::getTablePrefix();

        $mode = OrbitInput::get('export', 'print');
        $user = $this->loggedUser;
        $now = date('Y-m-d H:i:s');

        $merchant_id = \Merchant::where('user_id', $user->user_id)->first()->merchant_id;

        $promotions = Promotion::excludeDeleted('promotions')
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
                    DB::raw('retailer.*'),
                    DB::raw('product.*'),
                    DB::raw('cat1.category_name as family_name1'),
                    DB::raw('cat2.category_name as family_name2'),
                    DB::raw('cat3.category_name as family_name3'),
                    DB::raw('cat4.category_name as family_name4'),
                    DB::raw('cat5.category_name as family_name5'),
                    "promotion_rules.rule_type as rule_type",
                    "promotion_rules.discount_value as discount_value",
                    "promotion_rules.discount_object_type as discount_object_type"
            )
            ->leftJoin(DB::raw("(
                        select p.promotion_id as promotion_id1, 
                            CASE
                            WHEN
                                (p.is_all_retailer = 'Y')
                            THEN
                                'All Retailer' 
                            ELSE 
                                GROUP_CONCAT(r.`name` SEPARATOR ', ')
                            END AS retailer_list
                        from {$prefix}promotions p
                        left join {$prefix}promotion_retailer pr on pr.promotion_id = p.promotion_id
                        left join {$prefix}merchants r on r.merchant_id = pr.retailer_id
                        where p.merchant_id = {$merchant_id} and p.status != 'deleted'
                        group by p.promotion_id 
                    ) AS retailer "), function ($q) {
                        $q->on( DB::raw('retailer.promotion_id1'), '=', 'promotions.promotion_id' );
                    })
            ->leftJoin(DB::raw(" (
                        select p.promotion_id as promotion_id2, 
                            CASE
                            WHEN
                                (pr.is_all_product_discount = 'Y')
                            THEN
                                'All Product' 
                            ELSE 
                                GROUP_CONCAT(p1.`product_name` SEPARATOR ', ')
                            END AS product_name
                        from {$prefix}promotions p
                        left join {$prefix}promotion_rules pr on pr.promotion_id = p.promotion_id
                        left join {$prefix}promotion_product prr on prr.promotion_rule_id = pr.promotion_rule_id and prr.object_type = 'discount'
                        left join {$prefix}products p1 on p1.product_id = prr.product_id
                        where p.merchant_id = {$merchant_id} and p.status != 'deleted'
                        group by p.promotion_id 
                    ) AS product "), function ($q) {
                        $q->on( DB::raw('product.promotion_id2'), '=', 'promotions.promotion_id' );
                    })
            ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id')
            // ->leftJoin('promotion_retailer', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
            // ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
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

        // Filter promotion by Ids
        OrbitInput::get('promotion_id', function($promotionIds) use ($promotions)
        {
            $promotions->whereIn('promotions.promotion_id', $promotionIds);
        });

        // Filter promotion by merchant Ids
        OrbitInput::get('merchant_id', function ($merchantIds) use ($promotions) {
            $promotions->whereIn('promotions.merchant_id', $merchantIds);
        });

        // Filter promotion by promotion name
        OrbitInput::get('promotion_name', function($promotionname) use ($promotions)
        {
            $promotions->whereIn('promotions.promotion_name', $promotionname);
        });

        // Filter promotion by matching promotion name pattern
        OrbitInput::get('promotion_name_like', function($promotionname) use ($promotions)
        {
            $promotions->where('promotions.promotion_name', 'like', "%$promotionname%");
        });

        // Filter promotion by promotion type
        OrbitInput::get('promotion_type', function($promotionTypes) use ($promotions)
        {
            $promotions->whereIn('promotions.promotion_type', $promotionTypes);
        });

        // Filter promotion by description
        OrbitInput::get('description', function($description) use ($promotions)
        {
            $promotions->whereIn('promotions.description', $description);
        });

        // Filter promotion by matching description pattern
        OrbitInput::get('description_like', function($description) use ($promotions)
        {
            $promotions->where('promotions.description', 'like', "%$description%");
        });

        // Filter promotion by begin date
        OrbitInput::get('begin_date', function($begindate) use ($promotions)
        {
            $promotions->where('promotions.begin_date', '<=', $begindate);
        });

        // Filter promotion by end date
        OrbitInput::get('end_date', function($enddate) use ($promotions)
        {
            $promotions->where('promotions.end_date', '>=', $enddate);
        });

        // Filter promotion by end_date for begin
        OrbitInput::get('expiration_begin_date', function($begindate) use ($promotions)
        {
            $promotions->where('promotions.end_date', '>=', $begindate)
                       ->where('promotions.is_permanent', 'N');
        });

        // Filter promotion by end_date for end
        OrbitInput::get('expiration_end_date', function($enddate) use ($promotions)
        {
            $promotions->where('promotions.end_date', '<=', $enddate)
                       ->where('promotions.is_permanent', 'N');
        });

        // Filter promotion by is permanent
        OrbitInput::get('is_permanent', function ($ispermanent) use ($promotions) {
            $promotions->whereIn('promotions.is_permanent', $ispermanent);
        });

        // Filter promotion by status
        OrbitInput::get('status', function ($statuses) use ($promotions) {
            $promotions->whereIn('promotions.status', $statuses);
        });

        // Filter promotion by created_at for begin_date
        OrbitInput::get('created_begin_date', function($begindate) use ($promotions)
        {
            $promotions->where('promotions.created_at', '>=', $begindate);
        });

        // Filter promotion by created_at for end_date
        OrbitInput::get('created_end_date', function($enddate) use ($promotions)
        {
            $promotions->where('promotions.created_at', '<=', $enddate);
        });

        // Filter promotion rule by rule type
        OrbitInput::get('rule_type', function ($ruleTypes) use ($promotions) {
            $promotions->whereHas('promotionrule', function($q) use ($ruleTypes) {
                $q->whereIn('rule_type', $ruleTypes);
            });
        });

        // Filter promotion rule by discount_value
        OrbitInput::get('discount_value', function ($discount_value) use ($promotions) {
            $promotions->whereHas('promotionrule', function($q) use ($discount_value) {
                $q->where(function ($q) use ($discount_value) {
                    $q->whereIn('discount_value', $discount_value);

                    // to filter percentage value.
                    $discount_value_in_percentage = array();
                    foreach($discount_value as $a) {
                        $discount_value_in_percentage[] = $a/100;
                    }
                    $q->orWhereIn('discount_value', $discount_value_in_percentage);
                });
            });
        });

        // Filter promotion rule by discount object type
        OrbitInput::get('discount_object_type', function ($discountObjectTypes) use ($promotions) {
            $promotions->whereHas('promotionrule', function($q) use ($discountObjectTypes) {
                $q->whereIn('discount_object_type', $discountObjectTypes);
            });
        });

        // Filter promotion rule by discount object id1
        OrbitInput::get('discount_object_id1', function ($discountObjectId1s) use ($promotions) {
            $promotions->whereHas('promotionrule', function($q) use ($discountObjectId1s) {
                $q->whereIn('discount_object_id1', $discountObjectId1s);
            });
        });

        // Filter promotion rule by discount object id2
        OrbitInput::get('discount_object_id2', function ($discountObjectId2s) use ($promotions) {
            $promotions->whereHas('promotionrule', function($q) use ($discountObjectId2s) {
                $q->whereIn('discount_object_id2', $discountObjectId2s);
            });
        });

        // Filter promotion rule by discount object id3
        OrbitInput::get('discount_object_id3', function ($discountObjectId3s) use ($promotions) {
            $promotions->whereHas('promotionrule', function($q) use ($discountObjectId3s) {
                $q->whereIn('discount_object_id3', $discountObjectId3s);
            });
        });

        // Filter promotion rule by discount object id4
        OrbitInput::get('discount_object_id4', function ($discountObjectId4s) use ($promotions) {
            $promotions->whereHas('promotionrule', function($q) use ($discountObjectId4s) {
                $q->whereIn('discount_object_id4', $discountObjectId4s);
            });
        });

        // Filter promotion rule by discount object id5
        OrbitInput::get('discount_object_id5', function ($discountObjectId5s) use ($promotions) {
            $promotions->whereHas('promotionrule', function($q) use ($discountObjectId5s) {
                $q->whereIn('discount_object_id5', $discountObjectId5s);
            });
        });

        // Filter promotion rule by matching discount object name pattern (product or family link)
        OrbitInput::get('discount_object_name_like', function ($discount_object_name) use ($promotions) {
            $promotions->whereHas('promotionrule', function ($q) use ($discount_object_name) {
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

        // Filter promotion retailer by retailer id
        OrbitInput::get('retailer_id', function ($retailerIds) use ($promotions) {
            $promotions->whereHas('retailers', function($q) use ($retailerIds) {
                $q->whereIn('retailer_id', $retailerIds);
            });
        });

        // Add new relation based on request
        OrbitInput::get('with', function ($with) use ($promotions) {
            $with = (array) $with;

            foreach ($with as $relation) {
                if ($relation === 'retailers') {
                    $promotions->with('retailers');
                } elseif ($relation === 'product') {
                    $promotions->with('promotionrule.discountproduct');
                } elseif ($relation === 'family') {
                    $promotions->with('promotionrule.discountcategory1', 'promotionrule.discountcategory2', 'promotionrule.discountcategory3', 'promotionrule.discountcategory4', 'promotionrule.discountcategory5');
                }
            }
        });

        // Clone the query builder which still does not include the take,
        // skip, and order by
        $_promotions = clone $promotions;

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
            $promotions->orderBy('display_discount_type', $sortMode);
            $promotions->orderBy('display_discount_value', $sortMode);
        } else {
            $promotions->orderBy($sortBy, $sortMode);
        }


        $totalRec = RecordCounter::create($_promotions)->count();

        $this->prepareUnbufferedQuery();

        $sql = $promotions->toSql();
        $binds = $promotions->getBindings();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($binds);
        
        $pageTitle = 'Promotion';
        switch ($mode) {
            case 'csv':
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . OrbitText::exportFilename($pageTitle));

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Promotion List', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Promotion', $totalRec, '', '', '', '','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Name', 'Expiration Date', 'Retailer', 'Discount Type', 'Discount Value', 'Product or Family Link', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $expiration_date = $this->printExpirationDate($row);
                    $discount_type = $this->printDiscountType($row);
                    $productfamilylink = $this->printProductFamilyLink($row);

                    printf("\"%s\",\"%s\", %s,\"%s\",\"%s\", %s,\"%s\",\"%s\"\n", '', $this->printUtf8($row->promotion_name), $expiration_date, $this->printUtf8($row->retailer_list), $discount_type, $row->discount_value, $productfamilylink, $row->status);
                }
                break;

            case 'print':
            default:
                $me = $this;
                require app_path() . '/views/printer/list-promotion-view.php';
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
        switch ($promotion->rule_type) {
            case 'cart_discount_by_value':
                $result = 'Cart Discount By Value';
                break;

            case 'cart_discount_by_percentage':
                $result = 'Cart Discount By Percentage';
                break;

            case 'product_discount_by_value':
                $result = 'Product Discount By Value';
                break;

            case 'product_discount_by_percentage':
                $result = 'Product Discount By Percentage';
                break;

            case 'new_product_price':
                $result = 'New Product Price';
                break;

            default:
                $result = '';
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
                if($promotion->end_date==NULL || empty($promotion->end_date)){
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