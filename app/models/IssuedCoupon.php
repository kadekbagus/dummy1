<?php
class IssuedCoupon extends Eloquent
{
    use GeneratedUuidTrait;
    /**
     * IssuedCoupon Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    const ISSUE_COUPON_INCREMENT = 111111;

    protected $table = 'issued_coupons';

    protected $primaryKey = 'issued_coupon_id';

    public function coupon()
    {
        return $this->belongsTo('Coupon', 'promotion_id', 'promotion_id');
    }

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function issuerretailer()
    {
        return $this->belongsTo('Retailer', 'issuer_retailer_id', 'merchant_id');
    }

    public static function generateIssuedCouponCode()
    {
        $last  = static::orderBy('issued_coupon_code', 'desc')->first();
        $exists = function ($max) {
            static::where('issued_coupon_code', $max)->exists();
        };

        if (is_null($last))
        {
            $max = static::ISSUE_COUPON_INCREMENT;
        } else  {
            $max = (int) $last->issued_coupon_code + 1;
        }

        while ($exists($max))
        {
            $max = $max + 1;

        }

        return $max;
    }

}
