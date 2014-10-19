<?php

class Apikey extends Eloquent
{
    protected $primaryKey = 'apikey_id';

    protected $table = 'apikeys';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }
}
