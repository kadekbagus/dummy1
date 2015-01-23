<?php
/**
 * Employee class for represent the structure of employees table.
 *
 * @author Rio Astamal <me@rioastamal.net?
 */
class Employee extends Eloquent
{
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
        return $this->belongsToMany('Retailer', 'employee_retailer', 'employee_id', 'retailer_id');
    }
}
