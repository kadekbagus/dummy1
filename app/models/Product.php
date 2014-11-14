<?php

class Product extends Eloquent
{
    /**
     * Product Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    use ModelStatusTrait;

    protected $table = 'products';

    protected $primaryKey = 'product_id';

    public function categories()
    {
        return $this->belongsToMany('Category', 'product_category', 'product_id', 'product_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function retailer()
    {
        return $this->belongsTo('Retailer', 'retailer_id', 'merchant_id');
    }

    public function scopeNew($query)
    {
        return $query->where('products.is_new', '=', 'yes');
    }

    public function scopeDependOnStock($query)
    {
        return $query->where('products.depend_on_stock', '=', 'yes');
    }

}
