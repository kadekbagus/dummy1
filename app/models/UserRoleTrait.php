<?php
/**
 * Traits for storing role method that used by User
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 *
 * @property Merchant[] $merchants
 * @property Role $role
 *
 * @method static \Illuminate\Database\Eloquent\Builder consumers()
 */
trait UserRoleTrait
{
    /**
     * Flag to indicate whether the prepareMerchant() has been called.
     *
     * @var boolean
     */
    protected $prepareMerchantCalled = FALSE;

    /**
     * Flag to indicate whether the prepareEmployeeRetailer() has been called.
     *
     * @var boolean
     */
    protected $prepareEmployeeRetailerCalled = FALSE;


    /**
     * Filter User by Consumer Role
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @param Illuminate\Database\Eloquent\Builder $query
     * @return Illuminate\Database\Eloquent\Builder
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
     *
     * @param Illuminate\Database\Eloquent\Builder $query
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function scopeMerchantOwners($query)
    {
        return $query->whereHas('role', function($q){
            $q->where('role_name', '=', 'merchant owner');
        });
    }

    /**
     * Query builder for joining consumer to user_details table, so it can be
     * filtered based on merchant_id or retailer_id (shop).
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param Illuminate\Database\Eloquent\Builder $query
     * @return Illuminate\Database\Eloquent\Builder
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
     *
     * @param Illuminate\Database\Eloquent\Builder $query
     * @param array|string $ids array of IDs or string of comma separated IDs
     * @return Illuminate\Database\Eloquent\Builder
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
     *
     * @param Illuminate\Database\Eloquent\Builder $query
     * @param array|string $ids array of IDs or string of comma separated IDs
     * @return Illuminate\Database\Eloquent\Builder
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

    /**
     * Query builder for joining employee to to employee_retailer table,
     * so it can be filtered based on merchant_id or retailer_id (shop).
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param Illuminate\Database\Eloquent\Builder $query
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function scopePrepareEmployeeRetailer($query)
    {
        // Set the flag to TRUE, so it will not be called multiple times implicitly
        $this->prepareEmployeeRetailerCalled = TRUE;

        return $query->select('users.*', 'employees.position')
                     ->join('employees', 'employees.user_id', '=', 'users.user_id')
                     ->join('employee_retailer', 'employees.employee_id', '=', 'employee_retailer.employee_id');
    }

    /**
     * Filter employee based on retailer id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param Illuminate\Database\Eloquent\Builder $query
     * @param array|string $ids array of IDs or string of comma separated IDs
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function scopeEmployeeRetailerIds($query, $ids)
    {
        if ($this->prepareEmployeeRetailerCalled === FALSE) {
            $this->scopePrepareEmployeeRetailer($query);
        }

        // If the ids not array try to split by comma
        // the input should be in format i.e. '1,2,3'
        if (! is_array($ids)) {
            $ids = explode(',', (string)$ids);
            $ids = array_map('trim', $ids);
        }

        if (! empty($ids)) {
            return $query->whereIn('employee_retailer.retailer_id', $ids);
        }

        return $query;
    }

    /**
     * Filter employee based on merchant id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param Illuminate\Database\Eloquent\Builder $query
     * @param array|string $ids array of IDs or string of comma separated IDs
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function scopeEmployeeMerchantIds($query, $ids)
    {
        if ($this->prepareEmployeeRetailerCalled === FALSE) {
            $this->scopePrepareEmployeeRetailer($query);
        }

        // If the ids not array try to split by comma
        // the input should be in format i.e. '1,2,3'
        if (! is_array($ids)) {
            $ids = explode(',', (string)$ids);
            $ids = array_map('trim', $ids);
        }

        if (! empty($ids)) {
            return $query->join('merchants', function($join) {
                                $join->on('merchants.merchant_id', '=', 'employee_retailer.retailer_id');
                                $join->on('merchants.object_type', '=', DB::raw("'retailer'"));
                       })->whereIn('merchants.parent_id', $ids)
                         ->groupBy('users.user_id');
        }

        return $query;
    }

    /**
     * Filter employee based on employee_id_char.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param Illuminate\Database\Eloquent\Builder $query
     * @param array|string $ids array of IDs or string of comma separated IDs
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function scopeEmployeeIdChars($query, $ids)
    {
        if ($this->prepareEmployeeRetailerCalled === FALSE) {
            $this->scopePrepareEmployeeRetailer($query);
        }

        // If the ids not array try to split by comma
        // the input should be in format i.e. '1,2,3'
        if (! is_array($ids)) {
            $ids = explode(',', (string)$ids);
            $ids = array_map('trim', $ids);
        }

        if (! empty($ids)) {
            return $query->whereIn('employees.employee_id_char', $ids);
        }

        return $query;
    }

    /**
     * Filter employee based on employee_id_char pattern.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param Illuminate\Database\Eloquent\Builder $query
     * @param string $pattern
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function scopeEmployeeIdCharLike($query, $pattern)
    {
        if ($this->prepareEmployeeRetailerCalled === FALSE) {
            $this->scopePrepareEmployeeRetailer($query);
        }

        return $query->where('emplolyees.employee_id_char', 'like', "%$pattern%");
    }

    /**
     * Super admin check.
     *
     * @Todo: Prevent query.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return boolean
     */
    public function isSuperAdmin()
    {
        $superAdmin = 'super admin';

        return strtolower($this->role->role_name) === $superAdmin;
    }

    /**
     * Merchant Owner check.
     *
     * @author Tian <tian@dominopos.com>
     *
     * @return boolean
     */
    public function isMerchantOwner()
    {
        $role = 'merchant owner';

        return strtolower($this->role->role_name) === $role;
    }

    /**
     * Consumer check.
     *
     * @author Tian <tian@dominopos.com>
     *
     * @return boolean
     */
    public function isConsumer()
    {
        $role = 'consumer';

        return strtolower($this->role->role_name) === $role;
    }

    /**
     * Get list of retailer ids owned by this user. This is f*cking wrong,
     * normally I hate doing loop on query.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    public function getMyRetailerIds()
    {
        $merchants = $this->merchants;

        $merchantIds = [];
        foreach ($merchants as $merchant) {
            $merchantIds[] = $merchant->merchant_id;
        }

        if (empty($merchantIds)) {
            return [];
        }

        $retailerIds = DB::table('merchants')->whereIn('parent_id', $merchantIds)
                       ->lists('merchant_id');

        return $retailerIds;
    }

    /**
     * Get list of merchant ids owned by this user. This is f*cking wrong,
     * normally I hate doing loop on query.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    public function getMyMerchantIds()
    {
        $merchants = $this->merchants;

        $merchantIds = [];
        foreach ($merchants as $merchant) {
            $merchantIds[] = $merchant->merchant_id;
        }

        if (empty($merchantIds)) {
            return [];
        }

        return $merchantIds;
    }

    /**
     * Super admin check.
     *
     * @Todo: Prevent query.
     *
     * @Param string $rolename
     * @return boolean
     */
    public function isRoleName($rolename)
    {
        $rolename = strtolower($rolename);

        return strtolower($this->role->role_name) === $rolename;
    }
}
