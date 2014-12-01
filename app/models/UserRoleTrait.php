<?php
/**
 * Traits for storing role method that used by User
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */
trait UserRoleTrait
{
   /**
     * Filter User by Consumer Role
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeConsumers($query)
    {
        return $query->whereHas('role', function($q){
            $q->where('role_name', '=', 'consumer'));
        });
    }

    /**
     * Filter User by Merchant Role
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeMerchants($query)
    {
        return $query->whereHas('role', function($q){
            $q->where('role_name', '=', 'merchant-owner'));
        });
    }   
}
