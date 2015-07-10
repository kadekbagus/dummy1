<?php

use OrbitRelation\BelongsToManyWithUUIDPivot;

class Permission extends Eloquent
{
    use GeneratedUuidTrait;

    public $incrementing = false;
    
    protected $primaryKey = 'permission_id';

    protected $table = 'permissions';

    public function users()
    {
        return (new BelongsToManyWithUUIDPivot((new User())->newQuery(), $this, 'custom_permission', 'permission_id', 'user_id', 'custom_permission_id', 'users'))->withPivot('allowed');
    }

    public function roles()
    {
        return (new BelongsToManyWithUUIDPivot((new Role())->newQuery(), $this, 'permission_role', 'permission_id', 'role_id', 'permission_role_id', 'roles'))->withPivot('allowed');
    }
}
