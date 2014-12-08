<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;

class User extends Eloquent implements UserInterface
{
    use UserTrait;
    use ModelStatusTrait;
    use UserRoleTrait;

    protected $primaryKey = 'user_id';

    protected $table = 'users';

    protected $hidden = array('user_password');

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

    public function userdetail()
    {
        return $this->hasOne('UserDetail', 'user_id', 'user_id');
    }

    public function getFullName()
    {
        return $this->user_firstname . ' ' . $this->user_lastname;
    }

    public function merchants()
    {
        return $this->hasMany('Merchant', 'user_id', 'user_id');
    }

    public function lastVisitedShop(){
        return $this->belongsToMany('Retailer', 'user_details', 'user_id', 'last_visit_shop_id');
    }

    /**
     * Tells Laravel the name of our password field so Laravel does not uses
     * its default `password` field. Our field name is `user_password`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->user_password;
    }
}
