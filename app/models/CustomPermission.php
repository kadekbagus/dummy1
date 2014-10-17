<?php

class CustomPermission extends Eloquent
{
    protected $primaryKey = 'custom_permission_id';

    protected $table = 'custom_permission';

    public function users()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function permissions()
    {
        return $this->belongsTo('Permission', 'permission_id', 'permission_id');
    }
}
