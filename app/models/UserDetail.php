<?php

class UserDetail extends Eloquent
{
    protected $table = 'user_details';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }
}
