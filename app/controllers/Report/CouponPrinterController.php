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
            ->excludeDeleted()
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
                ")
            )
            ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');

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

        $_coupons = clone $coupons;

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
                printf("%s,%s,%s,%s,%s,%s,%s,%s\n", '', 'Name', 'Expiration Date', 'Redeem Retailer', 'Discount Type', 'Discount Value', 'Product or Family Link', 'Status');
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
                $pageTitle = 'Coupon';
                require app_path() . '/views/printer/list-coupon-view.php';
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