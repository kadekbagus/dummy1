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


        $promotions = Promotion::with('promotionrule')
            ->excludeDeleted()
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
                ")
            )
            ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');

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

        // Filter promotion by is permanent
        OrbitInput::get('is_permanent', function ($ispermanent) use ($promotions) {
            $promotions->whereIn('promotions.is_permanent', $ispermanent);
        });

        // Filter promotion by status
        OrbitInput::get('status', function ($statuses) use ($promotions) {
            $promotions->whereIn('promotions.status', $statuses);
        });

        // Filter promotion rule by rule type
        OrbitInput::get('rule_type', function ($ruleTypes) use ($promotions) {
            $promotions->whereHas('promotionrule', function($q) use ($ruleTypes) {
                $q->whereIn('rule_type', $ruleTypes);
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

        // Filter promotion retailer by retailer id
        OrbitInput::get('retailer_id', function ($retailerIds) use ($promotions) {
            $promotions->whereHas('retailers', function($q) use ($retailerIds) {
                $q->whereIn('retailer_id', $retailerIds);
            });
        });

        $_promotions = clone $promotions;

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
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Name', 'Expiration Date', 'Retailer', 'Discount Type', 'Discount Value', 'Product Family Link', 'Status');
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', '', '', '', '', '', '','');
                
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {

                    $discount_type = $this->printDiscountType($row);
                    $expiration_date = $this->printExpirationDate($row);

                    printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', $row->promotion_name, $expiration_date, '', $discount_type, '', '', $row->status);
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
                $date = $promotion->end_date;
                $date = explode(' ',$date);
                $time = strtotime($date[0]);
                $newformat = date('d M Y',$time);
                $result = $newformat;
        }

        return $result;
    }
}