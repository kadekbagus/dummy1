<?php

class MerchantTax extends Eloquent
{
    /**
     * Merchant Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */

    protected $table = 'merchant_taxes';

    protected $primaryKey = 'merchant_tax_id';

    public function productstax1()
    {
        $this->hasMany('Product', 'merchant_tax_id', 'merchant_tax_id1');
    }

    public function productstax2()
    {
        $this->hasMany('Product', 'merchant_tax_id', 'merchant_tax_id2');
    }

    public function merchant()
    {
        $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function modifier()
    {
        $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function creator()
    {
        $this->belongsTo('User', 'created_by', 'user_id');
    }
}