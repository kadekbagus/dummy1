<?php

class UserDetail extends Eloquent
{
    use ModelStatusTrait;
 
    protected $table = 'user_details';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id')->where('users.status', '=', 'active');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->join('users', 'user_details.user_id', '=', 'users.user_id')->where('users.status', '=', 'active');
    }
}
