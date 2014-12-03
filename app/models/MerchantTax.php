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

        // This will filter only user which belongs to merchant
        $builder->where('merchants.user_id', $user->user_id);

        return $builder;
    }
}