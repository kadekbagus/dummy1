<?php

class Cart extends Eloquent
{
    use GeneratedUuidTrait;
    /**
     * Cart Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    use ModelStatusTrait;

    const CART_INCREMENT = 3000;

    protected $table = 'carts';

    protected $primaryKey = 'cart_id';

    public function details()
    {
        return $this->hasMany('CartDetail', 'cart_id', 'cart_id');
    }

    public function users()
    {
        return $this->belongsTo('User', 'customer_id', 'user_id');
    }

    public static function generateCartCode()
    {
        $time   = time();
        $cartCode   = static::CART_INCREMENT . $time;

        $exists = function($cartCode) {
            return static::where('cart_code', $cartCode)->exists();
        };

        while($exists($cartCode))
        {
            $cartCode = (static::CART_INCREMENT + 1) . $time;
        };

        return $cartCode;
    }

}
