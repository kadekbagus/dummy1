<?php

class Apikey extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $primaryKey = 'apikey_id';

    protected $table = 'apikeys';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }
}
