<?php

class Category extends Eloquent
{
    /**
     * Category Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Tian <tian@dominopos.com>
     */
    use ModelStatusTrait;

    protected $table = 'categories';

    protected $primaryKey = 'category_id';

    public function products()
    {
        return $this->belongsToMany('Product', 'product_category', 'category_id', 'category_id');
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

    /**
     * Add Filter category based on user who request it.
     *
     * @author Tian <tian@dominopos.com>
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user Instance of object user
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

        // This will filter only categories which belongs to merchant
        // The merchant owner has an ability to view all products
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->whereRaw("{$prefix}categories.merchant_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.user_id=?)", array($user->user_id));
        });

        return $builder;
    }
}
