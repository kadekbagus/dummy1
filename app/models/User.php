<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;

class User extends Eloquent implements UserInterface
{
    use UserTrait;

    protected $primaryKey = 'user_id';

    protected $table = 'users';

    public function roles()
    {
        return $this->belongsTo('Role', 'user_role_id', 'role_id');
    }

    public function permissions()
    {
        return $this->belongsToMany('Permission', 'custom_permission', 'user_id', 'permission_id')->withPivot('allowed');
    }

    public function apiKeys()
    {
        return $this->hasMany('Apikey', 'user_id', 'user_id');
    }
}
