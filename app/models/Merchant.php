<?php

class Merchant extends Eloquent
{
    /**
     * Merchant Model
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

    protected $primaryKey = 'merchant_id';

    protected $table = 'merchants';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function retailers()
    {
        return $this->hasMany('Retailer', 'parent_id', 'merchant_id');
    }

    public function children()
    {
        return $this->retailer();
    }
}
