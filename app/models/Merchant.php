<?php

class Merchant extends Eloquent
{
    /**
     * Merchant Model
     *
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    use ModelStatusTrait;

    protected $primaryKey = 'merchant_id';

    protected $table = 'merchants';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function parent()
    {
        return $this->belongsTo('Merchant', 'parent_id', 'merchant_id');
    }

    public function children()
    {
        return $this->hasMany('Merchant', 'parent_id', 'merchant_id');
    }
}
