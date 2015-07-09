<?php
/**
 * Employee class for represent the structure of employees table.
 *
 * @author Rio Astamal <me@rioastamal.net?
 */
class Employee extends Eloquent
{

    use GeneratedUuidTrait;

    protected $table = 'employees';
    protected $primaryKey = 'employee_id';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Employee belongs to a User
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    /**
     * Employee could belongs to and has many retailers
     */
    public function retailers()
    {
        return (new \OrbitRelation\BelongsToManyWithUUIDPivot((new Retailer())->newQuery(), $this, 'employee_retailer', 'employee_id', 'retailer_id', 'employee_retailer_id', 'retailers'));
    }

    /**
     * Employee belongs to many merchant ids.
     *
     * @return array
     */
    public function getMyMerchantIds()
    {
        $empId = $this->employee_id;
        $prefix = DB::getTablePrefix();

        return DB::table('merchants')->whereRaw("merchant_id IN (SELECT `retailer_id`
                                                 from {$prefix}employee_retailer where `employee_id`=?)", [$empId])
                 ->where('object_type', 'retailer')
                 ->groupBy('parent_id')
                 ->lists('parent_id');
    }
}
