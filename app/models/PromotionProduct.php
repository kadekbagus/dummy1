<?php

class PromotionProduct extends Eloquent
{
    use GeneratedUuidTrait;
    /**
     * PromotionProduct Model
     *
     * @author kadek <kadek@dominopos.com>
     */

    protected $primaryKey = 'promotion_product_id';

    protected $table = 'promotion_product';

    public function promotionRule()
    {
        return $this->belongsTo('PromotionRule', 'promotion_rule_id', 'promotion_rule_id');
    }

    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'product_id');
    }
}
