<?php

class PromotionRule extends Eloquent
{
    protected $table = 'promotion_rules';

    protected $primaryKey = 'promotion_rule_id';

    public function promotion()
    {
        return $this->belongsTo('Promotion', 'promotion_id', 'promotion_id');
    }
}
