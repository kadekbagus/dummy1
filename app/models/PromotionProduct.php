<?php

class PromotionProduct extends Eloquent
{
    /**
     * PromotionProduct Model
     *
     * @author kadek <kadek@dominopos.com>
     */

    protected $primaryKey = 'promotion_product_id';

    protected $table = 'promotion_product';

    public function promotion()
    {
        return $this->belongsTo('Promotion', 'promotion_id', 'promotion_id');
    }

    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'product_id');
    }
}
