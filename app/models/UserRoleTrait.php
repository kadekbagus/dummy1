<?php
/**
 * Traits for storing role method that used by User
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */
trait UserRoleTrait
{
    /**
     * Flag to incidate whether the prepareMerchant() has been called.
     *
     * @var boolean
     */
    protected $prepareMerchantCalled = FALSE;

    /**
     * Filter User by Consumer Role
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeConsumers($query)
    {
        return $query->whereHas('role', function($q){
            $q->where('role_name', '=', 'consumer');
        });
    }

    /**
     * Filter User by Merchant Role
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeMerchantOwners($query)
    {
        return $query->whereHas('role', function($q){
            $q->where('role_name', '=', 'merchant-owner');
        });
    }

    /**
     * Query builder for joining consumer to user_details table, so it can be
     * filtered based on merchant_id or retailer_id (shop).
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopePrepareMerchant($query)
    {
        // Set the flag to TRUE, so it will not be called multiple times implicitly
        $this->prepareMerchantCalled = TRUE;

        return $query->select('users.*')
                     ->leftJoin('user_details', 'users.user_id', '=', 'user_details.user_id');
    }

    /**
     * Filter consumer based on merchant id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeMerchantIds($query, $ids)
    {
        if ($this->prepareMerchantCalled === FALSE) {
            $this->scopePrepareMerchant($query);
        }

        // If the ids not array try to split by comma
        // the input should be in format i.e. '1,2,3'
        if (! is_array($ids)) {
            $ids = explode(',', (string)$ids);
            $ids = array_map('trim', $ids);
        }

        if (! empty($ids)) {
            return $query->whereIn('user_details.merchant_id', $ids);
        }

        return $query;
    }

    /**
     * Filter consumer based on retailer id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeRetailerIds($query, $ids)
    {
        if ($this->prepareMerchantCalled === FALSE) {
            $this->scopePrepareMerchant($query);
        }

        // If the ids not array try to split by comma
        // the input should be in format i.e. '1,2,3'
        if (! is_array($ids)) {
            $ids = explode(',', (string)$ids);
            $ids = array_map('trim', $ids);
        }

        if (! empty($ids)) {
            return $query->whereIn('user_details.retailer_id', $ids);
        }

        return $query;
    }
}
