<?php
use OrbitRelation\BelongsToManyWithUUIDPivot;

class Coupon extends Eloquent
{
    use GeneratedUuidTrait;
    /**
     * Coupon Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Use Trait PromotionTypeTrait so we only displaying records with value
     * `is_coupon` = 'N'
     */
    use PromotionCouponTrait;

    /**
     * Column name which determine the type of Promotion or Coupon.
     */
    const OBJECT_TYPE = 'is_coupon';

    protected $table = 'promotions';

    protected $primaryKey = 'promotion_id';

    public function couponrule()
    {
        return $this->hasOne('CouponRule', 'promotion_id', 'promotion_id');
    }

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function issueretailers()
    {
        return (new BelongsToManyWithUUIDPivot((new Retailer())->newQuery(), $this, 'promotion_retailer', 'promotion_id', 'retailer_id', 'promotion_retailer_id', 'issueretailers'));
    }

    public function redeemretailers()
    {
        return (new BelongsToManyWithUUIDPivot((new Retailer())->newQuery(), $this, 'promotion_retailer_redeem', 'promotion_id', 'retailer_id', 'promotion_retailer_redeem_id', 'redeemretailers'));
    }

    public function issuedcoupons()
    {
        return $this->hasMany('IssuedCoupon', 'promotion_id', 'promotion_id');
    }

    public function products()
    {
        return $this->belongsToMany('Product', 'promotion_product', 'promotion_id', 'product_id');
    }

    /**
     * Add Filter coupons based on user who request it.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user Instance of object user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAllowedForUser($builder, $user)
    {
        // Super admin allowed to see all entries
        $superAdmin = Config::get('orbit.security.superadmin');
        if (empty($superAdmin))
        {
            $superAdmin = array('super admin');
        }

        // Transform all array into lowercase
        $superAdmin = array_map('strtolower', $superAdmin);
        $userRole = trim(strtolower($user->role->role_name));
        if (in_array($userRole, $superAdmin))
        {
            // do nothing return as is
            return $builder;
        }

        // This will filter only coupons which belongs to merchant
        // The merchant owner has an ability to view all coupons
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->whereRaw("{$prefix}promotions.merchant_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.user_id=? and m2.object_type='merchant')", array($user->user_id));
        });

        return $builder;
    }

    /**
     * Add Filter coupons based on user who request it. (Should be used on view only)
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user Instance of object user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAllowedForViewOnly($builder, $user)
    {
        // Super admin and Consumer allowed to see all entries
        // Weird? yeah this is supposed to call on merchant portal only
        $superAdmin = Config::get('orbit.security.superadmin');
        if (empty($superAdmin))
        {
            $superAdmin = array('super admin', 'consumer');
        }

        // Transform all array into lowercase
        $superAdmin = array_map('strtolower', $superAdmin);
        $userRole = trim(strtolower($user->role->role_name));
        if (in_array($userRole, $superAdmin))
        {
            // do nothing return as is
            return $builder;
        }

        // This will filter only coupons which belongs to merchant
        // The merchant owner has an ability to view all coupons
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->whereRaw("{$prefix}promotions.merchant_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.user_id=? and m2.object_type='merchant')", array($user->user_id));
        });

        return $builder;
    }

    /**
     * Coupon has many uploaded media.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'promotion_id')
                    ->where('object_name', 'coupon');
    }

    /**
     * Accessor for empty product image
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @param string $value - image path
     * @return string $value
     */
    public function getImageAttribute($value)
    {
        if (empty($value)) {
            return 'mobile-ci/images/default_product.png';
        }
        return ($value);
    }

    /**
     * Scope to determine coupon with transaction detail coupon and add custom
     * attribute named 'has_transaction' which hold value 'yes' or 'no'.
     *
     * @author kadek <kadek@dominopos.com>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIncludeTransactionStatus($builder)
    {
        $prefix = DB::getTablePrefix();
        $hasTransactionSelect = DB::raw("
            case
                when {$prefix}transactions.transaction_id is null then 'no'
                else 'yes'
            end as has_transaction
        ");
        return $builder->addSelect($hasTransactionSelect)
                       ->leftJoin('transaction_detail_coupons', 'transaction_detail_coupons.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('transactions', function($join) {
                             $join->where('transactions.status', '!=', 'deleted');
                             $join->on('transactions.transaction_id', '=', 'transaction_detail_coupons.transaction_id');
                        })
                       ->groupBy('promotions.promotion_id');
    }
}
