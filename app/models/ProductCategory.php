<?php

class ProductCategory extends Eloquent
{
    /**
     * ProductCategory Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */

    protected $primaryKey = 'product_category_id';

    protected $table = 'product_category';

    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'product_id');
    }

    public function category()
    {
        return $this->belongsTo('Category', 'category_id', 'category_id');
    }
}
