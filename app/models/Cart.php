<?php

class Cart extends Eloquent
{
    /**
     * Cart Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    use ModelStatusTrait;

    protected $table = 'carts';

    protected $primaryKey = 'cart_id';

    public function details()
    {
        return $this->hasMany('CartDetail', 'cart_id', 'cart_id');
    }

}