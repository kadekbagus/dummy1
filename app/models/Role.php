<?php

use OrbitRelation\BelongsToManyWithUUIDPivot;

class Role extends Eloquent
{

    use GeneratedUuidTrait;

    protected $primaryKey = 'role_id';

    protected $table = 'roles';

    public function users()
    {
        return $this->hasMany('User', 'user_role_id', 'role_id');
    }

    public function permissions()
    {
        return (new BelongsToManyWithUUIDPivot((new Permission())->newQuery(), $this, 'permission_role', 'role_id', 'permission_id', 'permission_role_id', 'permissions'))->withPivot('allowed');
    }
}
