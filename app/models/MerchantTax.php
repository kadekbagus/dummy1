<?php

class MerchantTax extends Eloquent
{
    use GeneratedUuidTrait;
    /**
     * Merchant Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    use ModelStatusTrait;

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

        // This will filter only taxes which belongs to merchant
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->whereRaw("{$prefix}merchant_taxes.merchant_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.user_id=?)", array($user->user_id));
        });

        return $builder;
    }

    /**
     * Scope to determine merchant tax with transaction detail tax and add custom
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
        return $builder->select('merchant_taxes.*', $hasTransactionSelect)
                       ->leftJoin('transaction_detail_taxes', 'transaction_detail_taxes.tax_id', '=', 'merchant_taxes.merchant_tax_id')
                        ->leftJoin('transactions', function($join) {
                             $join->where('transactions.status', '!=', 'deleted');
                             $join->on('transactions.transaction_id', '=', 'transaction_detail_taxes.transaction_id');
                        })
                       ->groupBy('merchant_taxes.merchant_tax_id');
    }
}
