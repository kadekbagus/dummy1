<?php

use OrbitRelation\BelongsToManyWithUUIDPivot;

class Retailer extends Eloquent
{

    use GeneratedUuidTrait;

    /**
     * Retailer Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Use Trait MerchantTypeTrait so we only displaying records with value
     * `object_type` = 'merchant'
     */
    use MerchantTypeTrait;

    /**
     * Column name which determine the type of Merchant or Retailer.
     */
    const OBJECT_TYPE = 'object_type';

    const ORID_INCREMENT = 2000;

    protected $primaryKey = 'merchant_id';

    protected $table = 'merchants';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'parent_id', 'merchant_id');
    }

    public function parent()
    {
        return $this->merchant();
    }

    /**
     * A Retailer has many and belongs to an employee
     */
    public function employees()
    {
        return (new BelongsToManyWithUUIDPivot((new Employee())->newQuery(), $this, 'employee_retailer', 'merchant_id', 'retailer_id', 'employee_retailer_id', 'employees'));
    }

    /**
     * Eagler load the count query. It is not very optimized but it works for now
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @credit http://laravel.io/forum/05-03-2014-eloquent-get-count-relation
     * @return int
     */
    public function merchantNumber()
    {
        // Basically we query Merchant which the its id are on the parent_id
        // of retailers
        return $this->belongsTo('Merchant', 'parent_id', 'merchant_id')
                    ->excludeDeleted()
                    ->selectRaw('merchant_id, count(*) as count')
                    ->groupBy('merchant_id');
    }

    public function getMerchantCountAttribute()
    {
        return $this->merchantNumber ? $this->merchantNumber->count : 0;
    }

    public function userNumber()
    {
        return $this->belongsTo('User', 'user_id', 'user_id')
                    ->excludeDeleted()
                    ->selectRaw('user_id, count(*) as count')
                    ->groupBy('user_id');
    }

    public function getUserCountAttribute()
    {
        return $this->userNumber ? $this->userNumber->count : 0;
    }

    /**
     * @return int
     */
    public static function generateOrid()
    {
        $time   = time();
        $orid   = static::ORID_INCREMENT . $time;

        $exists = function($orid) {
            return static::where('orid', $orid)->exists();
        };

        while($exists($orid))
        {
            $orid = (static::ORID_INCREMENT + 1) . $time;
        };

        return $orid;
    }

    /**
     * Add Filter retailers based on user who request it.
     *
     * @author Rio Astamal <me@rioastamal.net>
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

        // if user role is consumer, then do nothing.
        $consumer = array('consumer');
        $userRole = trim(strtolower($user->role->role_name));
        if (in_array($userRole, $consumer))
        {
            // do nothing return as is
            return $builder;
        }

        // This will filter only user which belongs to retailer or
        // merchant owner (the parent). The merchant owner has an ability
        // to view all retailers
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->where('merchants.user_id', $user->user_id)
                  ->orWhereRaw("{$prefix}merchants.parent_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.object_type='merchant' and
                                m2.status != 'deleted' and
                                m2.user_id=?)", array($user->user_id));
        });

        return $builder;
    }

    /**
     * Add Filter merchant based on transaction and users.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @param array $userIds - List of user ids
     * @param array $merchantIds - List of merchant Ids
     */
    public function scopeTransactionCustomerMerchantIds($builder, array $userIds, array $merchantIds)
    {
        return $builder->select('merchants.*')
                       // ->join('transactions', 'transactions.merchant_id', '=', 'merchants.merchant_id')
                       ->join('transactions', function($join) {
                            $join->on('transactions.retailer_id', '=', 'merchants.merchant_id');
                            $join->on('merchants.object_type', '=', DB::raw("'retailer'"));
                       })
                       ->where('transactions.status', 'paid')
                       ->whereIn('customer_id', $userIds)
                       ->whereIn('merchants.parent_id', $merchantIds)
                       ->groupBy('merchants.merchant_id');
    }


    public function scopeRetailerFromProduct($builder, $merchant_id, $product_id)
    {
        $builder->where('merchants.parent_id', $merchant_id)
                ->where('products.product_id', $product_id)
                ->where(function($q) {
                    $q->where('products.is_all_retailer', 'Y')
                      ->orWhere(function($q) {
                            $q->where(function($q) {
                                  $q->where('products.is_all_retailer', '!=', 'Y')
                                    ->orWhereNull('products.is_all_retailer');
                              })
                              ->whereNotNull('product_retailer.retailer_id');
                      });
        });

        return $builder;
    }


    public function scopeRetailerFromPromotion($builder, $merchant_id, $promotion_id)
    {
        $builder->where('merchants.parent_id', $merchant_id)
                ->where('promotions.promotion_id', $promotion_id)
                ->where('promotions.is_coupon', 'N')
                ->where(function($q) {
                    $q->where('promotions.is_all_retailer', 'Y')
                      ->orWhere(function($q) {
                            $q->where(function($q) {
                                  $q->where('promotions.is_all_retailer', '!=', 'Y')
                                    ->orWhereNull('promotions.is_all_retailer');
                              })
                              ->whereNotNull('promotion_retailer.retailer_id');
                      });
        });

        return $builder;
    }


    public function scopeRetailerFromCoupon($builder, $merchant_id, $coupon_id)
    {
        $builder->where('merchants.parent_id', $merchant_id)
                ->where('promotions.promotion_id', $coupon_id)
                ->where('promotions.is_coupon', 'Y')
                ->where(function($q) {
                    $q->where('promotions.is_all_retailer_redeem', 'Y')
                      ->orWhere(function($q) {
                            $q->where(function($q) {
                                  $q->where('promotions.is_all_retailer_redeem', '!=', 'Y')
                                    ->orWhereNull('promotions.is_all_retailer_redeem');
                              })
                              ->whereNotNull('promotion_retailer_redeem.retailer_id');
                      });
        });

        return $builder;
    }


    public function scopeRetailerFromEvent($builder, $merchant_id, $event_id)
    {
        $builder->where('merchants.parent_id', $merchant_id)
                ->where('events.event_id', $event_id)
                ->where(function($q) {
                    $q->where('events.is_all_retailer', 'Y')
                      ->orWhere(function($q) {
                            $q->where(function($q) {
                                  $q->where('events.is_all_retailer', '!=', 'Y')
                                    ->orWhereNull('events.is_all_retailer');
                              })
                             ->whereNotNull('event_retailer.retailer_id');
                      });
        });

        return $builder;
    }
}
