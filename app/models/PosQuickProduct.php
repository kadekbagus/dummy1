<?php
/**
 * Class for represent the activities table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class PosQuickProduct extends Eloquent
{
    protected $primaryKey = 'pos_quick_product_id';
    protected $table = 'pos_quick_products';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'product_id');
    }

    public function scopeJoinRetailer()
    {
        return $this->select('pos_quick_products.*')
                    ->join('products', 'products.product_id', '=', 'pos_quick_products.product_id')
                    ->join('product_retailer', 'product_retailer.product_id', '=', 'pos_quick_products.product_id')
                    ->groupBy('pos_quick_products.product_id');
    }
}
