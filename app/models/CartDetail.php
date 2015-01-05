<?php

class CartDetail extends Eloquent
{
    /**
     * Cart Detail Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    use ModelStatusTrait;

    protected $table = 'cart_details';

    protected $primaryKey = 'cart_detail_id';

    public function cart()
    {
        return $this->belongsTo('Cart', 'cart_id', 'cart_id');
    }

}