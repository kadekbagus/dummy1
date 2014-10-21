<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;

class User extends Eloquent implements UserInterface
{
    use UserTrait;
    use ModelStatusTrait;

    protected $primaryKey = 'user_id';

    protected $table = 'users';

    public function role()
    {
        return $this->belongsTo('Role', 'user_role_id', 'role_id');
    }

    public function permissions()
    {
        return $this->belongsToMany('Permission', 'custom_permission', 'user_id', 'permission_id')->withPivot('allowed');
    }

    public function apikey()
    {
        return $this->hasOne('Apikey', 'user_id', 'user_id')->where('apikeys.status','=','active');
    }
    
    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function userDetail()
    {
        return $this->hasOne('UserDetail', 'user_id', 'user_id');
    }

    public function getFullName()
    {
        return $this->user_firstname . " " . $this->user_lastname;
    }
}
