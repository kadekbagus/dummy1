<?php

class Category extends Eloquent
{
    /**
     * Category Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    use ModelStatusTrait;

    protected $table = 'categories';

    protected $primaryKey = 'category_id';

    public function products()
    {
        return $this->belongsToMany('Product', 'product_category', 'category_id', 'category_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function parent()
    {
        return $this->belongsTo('Category', 'parent_id', 'category_id');
    }    
}
