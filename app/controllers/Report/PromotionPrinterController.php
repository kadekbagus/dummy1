<?php namespace Report;

use Report\DataPrinterController;
use Config;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
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

        // Get the maximum record
        $maxRecord = (int) Config::get('orbit.pagination.promotion.max_record');
        if ($maxRecord <= 0) {
            // Fallback
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }
        }
        // Get default per page (take)
        $perPage = (int) Config::get('orbit.pagination.promotion.per_page');
        if ($perPage <= 0) {
            // Fallback
            $perPage = (int) Config::get('orbit.pagination.per_page');
            if ($perPage <= 0) {
                $perPage = 20;
            }
        }

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
                "), DB::raw("GROUP_CONCAT(`{$prefix}merchants`.`name`,' ',`{$prefix}merchants`.`city` SEPARATOR ' , ') as retailer_list"),
                    "promotion_rules.discount_value as discount_value"
            )
            ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id')
            ->leftJoin('promotion_retailer', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
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
            $promotions->where(function ($q) use ($begindate) {
                $q->where('promotions.end_date', '>=', $begindate)
                  ->orWhere('promotions.is_permanent', 'Y');
            });
        });

        // Filter promotion by end_date for end
        OrbitInput::get('expiration_end_date', function($enddate) use ($promotions)
        {
            $promotions->where(function ($q) use ($enddate) {
                $q->where('promotions.end_date', '<=', $enddate)
                  ->orWhere('promotions.is_permanent', 'Y');
            });
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

        $_promotions = clone $promotions;

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
        $promotions->take($take);

        $skip = 0;
        OrbitInput::get('skip', function($_skip) use (&$skip, $promotions)
        {
            if ($_skip < 0) {
                $_skip = 0;
            }

            $skip = $_skip;
        });
        if (($take > 0) && ($skip > 0)) {
            $promotions->skip($skip);
        }

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

        switch ($mode) {
            case 'csv':
                $filename = 'promotion-list-' . date('d_M_Y_HiA') . '.csv';
                @header('Content-Description: File Transfer');
                @header('Content-Type: text/csv');
                @header('Content-Disposition: attachment; filename=' . $filename);

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Promotion List', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Total Promotion', '', '', '', '', '','');

                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Name', 'Expiration Date', 'Retailer', 'Discount Type', 'Discount Value', 'Product Family Link', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $discount_type = $this->printDiscountType($row);
                    $expiration_date = $this->printExpirationDate($row);

                    printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n", '', $row->promotion_name, $expiration_date, '', $discount_type, '', '', $row->status);
                }
                break;

            case 'print':
            default:
                $me = $this;
                $pageTitle = 'Promotion';
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
        $return = '';
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
        $return = '';
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
                    $newformat = date('d M Y',$time);
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
        $return = '';
        switch ($promotion->display_discount_type) {
            case 'value':
                $result = number_format($promotion->discount_value);
                break;

            case 'percentage':
                $discount =  $promotion->discount_value*100;
                $result = $discount."%";
                break;
                
            default:
                $result = number_format($promotion->discount_value);
        }

        return $result;
    }
}