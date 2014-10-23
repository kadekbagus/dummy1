<?php

class UserDetail extends Eloquent
{
    use ModelStatusTrait;
 
    protected $table = 'user_details';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }
}
