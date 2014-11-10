<?php

class Retailer extends Eloquent
{
    /**
     * Retailer Model
     *
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    use ModelStatusTrait;

    protected $primaryKey = 'retailer_id';

    protected $table = 'retailers';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function parent()
    {
        return $this->belongsTo('Retailer', 'parent_id', 'retailer_id');
    }

    public function children()
    {
        return $this->hasMany('Retailer', 'parent_id', 'retailer_id');
    }
}
