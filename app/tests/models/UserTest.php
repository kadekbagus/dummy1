<?php
/**
 * Unit test for model ApiKey
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @copyright DominoPOS Ltd.
 */
class UserTest extends OrbitTestCase
{
    /**
     * Executed only once at the beginning of the test.
     */
    public static function setUpBeforeClass()
    {
        parent::createAppStatic();

        // Truncate the data just in case previous test was not clean up
        static::truncateData();

        // Get the prefix of the table name
        $apikey_table = static::$dbPrefix . 'apikeys';
        $user_table = static::$dbPrefix . 'users';
        $role_table = static::$dbPrefix . 'roles';
        $permission_table = static::$dbPrefix . 'permissions';
        $permission_role_table = static::$dbPrefix . 'permission_role';
        $custom_permission_table = static::$dbPrefix . 'custom_permission';

        // Insert dummy data on apikeys
        DB::statement("INSERT INTO `{$apikey_table}`
                (`apikey_id`, `api_key`, `api_secret_key`, `user_id`, `status`, `created_at`, `updated_at`)
                VALUES
                (1, 'abc123', 'abc12345678910', '1', 'active', '2014-10-19 20:02:01', '2014-10-19 20:03:01'),
                (2, 'bcd234', 'bcd23456789010', '2', 'active', '2014-10-19 20:02:02', '2014-10-19 20:03:02'),
                (3, 'cde345', 'cde34567890100', '3', 'active', '2014-10-19 20:02:03', '2014-10-19 20:03:03'),
                (4, 'def123', 'def12345678901', '1', 'deleted', '2014-10-19 20:02:04', '2014-10-19 20:03:04'),
                (5, 'efg212', 'efg09876543212', '4', 'blocked', '2014-10-19 20:02:05', '2014-10-19 20:03:05')"
        );

        $password = array(
            'john'      => Hash::make('john'),
            'smith'     => Hash::make('smith'),
            'chuck'     => Hash::make('chuck'),
            'optimus'   => Hash::make('optimus'),
            'panther'   => Hash::make('panther')
        );

        // Insert dummy data on users
        DB::statement("INSERT INTO `{$user_table}`
                (`user_id`, `username`, `user_password`, `user_email`, `user_firstname`, `user_lastname`, `user_last_login`, `user_ip`, `user_role_id`, `status`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'john', '{$password['john']}', 'john@localhost.org', 'John', 'Doe', '2014-10-20 06:20:01', '10.10.0.11', '1', 'active', '1', '2014-10-20 06:30:01', '2014-10-20 06:31:01'),
                ('2', 'smith', '{$password['smith']}', 'smith@localhost.org', 'John', 'Smith', '2014-10-20 06:20:02', '10.10.0.12', '3', 'active', '1', '2014-10-20 06:30:02', '2014-10-20 06:31:02'),
                ('3', 'chuck', '{$password['chuck']}', 'chuck@localhost.org', 'Chuck', 'Norris', '2014-10-20 06:20:03', '10.10.0.13', '3', 'active', '1', '2014-10-20 06:30:03', '2014-10-20 06:31:03'),
                ('4', 'optimus', '{$password['optimus']}', 'optimus@localhost.org', 'Optimus', 'Prime', '2014-10-20 06:20:04', '10.10.0.13', '3', 'blocked', '1', '2014-10-20 06:30:04', '2014-10-20 06:31:04'),
                ('5', 'panther', '{$password['panther']}', 'panther@localhost.org', 'Pink', 'Panther', '2014-10-20 06:20:05', '10.10.0.13', '3', 'deleted', '1', '2014-10-20 06:30:05', '2014-10-20 06:31:05')"
        );

        // Insert dummy data on roles
        DB::statement("INSERT INTO `{$role_table}`
                (`role_id`, `role_name`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'Super Admin', '1', NOW(), NOW()),
                ('2', 'Guest', '1', NOW(), NOW()),
                ('3', 'Customer', '1', NOW(), NOW())"
        );

        // Insert dummy data on permissions
        DB::statement("INSERT INTO `{$permission_table}`
                (`permission_id`, `permission_name`, `permission_label`, `permission_group`, `permission_group_label`, `permission_name_order`, `permission_group_order`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'login', 'Login', 'general', 'General', '0', '0', '1', NOW(), NOW()),
                ('2', 'view_user', 'View User', 'user', 'User', '1', '1', '1', NOW(), NOW()),
                ('3', 'create_user', 'Create User', 'user', 'User', '0', '1', '1', NOW(), NOW()),
                ('4', 'view_product', 'View Product', 'product', 'Product', '1', '2', '1', NOW(), NOW()),
                ('5', 'add_product', 'Add Product', 'product', 'Product', '0', '2', '1', NOW(), nOW())"
        );

        // Insert dummy data on permission_role
        DB::statement("INSERT INTO `{$permission_role_table}`
                (`permission_role_id`, `role_id`, `permission_id`, `allowed`, `created_at`, `updated_at`)
                VALUES
                ('1', '2', '1', 'yes', NOW(), NOW()),
                ('2', '3', '1', 'yes', NOW(), NOW()),
                ('3', '3', '2', 'no', NOW(), NOW()),
                ('4', '3', '3', 'no', NOW(), NOW()),
                ('5', '3', '4', 'no', NOW(), NOW()),
                ('6', '3', '5', 'no', NOW(), NOW())"
        );

        // Insert dummy data on custom_permission
        // view_user set to 'yes' for user 'ironman'
        DB::statement("INSERT INTO `{$custom_permission_table}`
                (`custom_permission_id`, `user_id`, `permission_id`, `allowed`, `created_at`, `updated_at`)
                VALUES
                ('1', '3', '2', 'yes', NOW(), NOW())"
        );
    }

    /**
     * Executed only once at the end of the test.
     */
    public static function tearDownAfterClass()
    {
        // do nothing
    }

    /**
     * Clear all data that has been inserted.
     */
    public static function truncateData()
    {
        $apikey_table = static::$dbPrefix . 'apikeys';
        $user_table = static::$dbPrefix . 'users';
        $role_table = static::$dbPrefix . 'roles';
        $permission_table = static::$dbPrefix . 'permissions';
        $permission_role_table = static::$dbPrefix . 'permission_role';
        $custom_permission_table = static::$dbPrefix . 'custom_permission';
        DB::unprepared("TRUNCATE `{$apikey_table}`;
                        TRUNCATE `{$user_table}`;
                        TRUNCATE `{$role_table}`;
                        TRUNCATE `{$custom_permission_table}`;
                        TRUNCATE `{$permission_role_table}`;
                        TRUNCATE `{$permission_table}`");
    }

    public function testObjectInstance()
    {
        $expect = 'User';
        $return = new User();
        $this->assertInstanceOf($expect, $return);
    }

    public function testNumberOfRecords()
    {
        $expect = 5;
        $return = User::count();
        $this->assertSame($expect, $return);
    }

    public function testRecordNumber3()
    {
        $user = User::find(3);
        $this->assertSame('3', (string)$user->user_id);
        $this->assertSame('chuck', $user->username);
        $this->assertSame('chuck@localhost.org', $user->user_email);
        $this->assertSame('Chuck', $user->user_firstname);
        $this->assertSame('Norris', $user->user_lastname);
        $this->assertSame('2014-10-20 06:20:03', $user->user_last_login);
        $this->assertSame('10.10.0.13', (string)$user->user_ip);
        $this->assertSame('3', (string)$user->user_role_id);
        $this->assertSame('active', $user->status);
        $this->assertSame('1', (string)$user->modified_by);
        $this->assertSame('2014-10-20 06:30:03', (string)$user->created_at);
        $this->assertSame('2014-10-20 06:31:03', (string)$user->updated_at);
    }

    public function testRelationshipExists_role_andReturn_BelongsTo()
    {
        $user = new User();
        $return = method_exists($user, 'role');
        $this->assertTrue($return);

        $expect = 'Illuminate\Database\Eloquent\Relations\BelongsTo';
        $return = $user->role();
        $this->assertInstanceOf($expect, $return);
    }

    public function testRelationshipData_role_recordNumber3()
    {
        $user = User::with(array('role', 'role.permissions'))->find(3);

        // The role of Chuck Norris should be customer
        $this->assertSame('3', (string)$user->role->role_id);
        $this->assertSame('Customer', $user->role->role_name);

        // Number of Customer permission should be 5
        $this->assertSame(5, count($user->role->permissions));

        // The login permission should  be 'yes'
        $loginPerm = $user->role->permissions->filter(function($perm)
        {
            return $perm->permission_name == 'login';
        });
        $this->assertSame('yes', $loginPerm->first()->pivot->allowed);

        // The view_user permission should be 'no'
        $viewUserPerm = $user->role->permissions->filter(function($perm)
        {
            return $perm->permission_name == 'view_user';
        });
        $this->assertSame('no', $viewUserPerm->first()->pivot->allowed);
    }

    public function testRelationshipExists_apikey_andReturn_HasOne()
    {
        $user = new User();
        $return = method_exists($user, 'apikey');
        $this->assertTrue($return);

        $expect = 'Illuminate\Database\Eloquent\Relations\HasOne';
        $return = $user->apikey();
        $this->assertInstanceOf($expect, $return);
    }

    public function testRelationshipData_apikey_recordNumber3()
    {
        $user = User::with(array('Apikey'))->find(3);
        $this->assertSame('3', (string)$user->apikey->apikey_id);
        $this->assertSame('cde345', $user->apikey->api_key);
        $this->assertSame('cde34567890100', $user->apikey->api_secret_key);
    }

    public function testRelationshipExists_modifier_andReturn_BelongsTo()
    {
        $user = new User();
        $return = method_exists($user, 'modifier');
        $this->assertTrue($return);

        $expect = 'Illuminate\Database\Eloquent\Relations\BelongsTo';
        $return = $user->modifier();
        $this->assertInstanceOf($expect, $return);
    }

    public function testRelationshipData_modifier_recordNumber3()
    {
        $user = User::with(array('Modifier'))->find(3);
        $this->assertSame('1', (string)$user->modifier->user_id);
        $this->assertSame('john', $user->modifier->username);
        $this->assertSame('john@localhost.org', $user->modifier->user_email);
    }

    public function testRelationshipExists_permissions_andReturn_BelongsToMany()
    {
        $user = new User();
        $return = method_exists($user, 'permissions');
        $this->assertTrue($return);

        $expect = 'Illuminate\Database\Eloquent\Relations\BelongsToMany';
        $return = $user->permissions();
        $this->assertInstanceOf($expect, $return);
    }

    public function testRelationshipData_permissions_recordNumber3()
    {
        $user = User::with(array('Permissions'))->find(3);

        // The view_user permission should be 'no'
        $viewUserPerm = $user->permissions->filter(function($perm)
        {
            return $perm->permission_name == 'view_user';
        });
        $this->assertSame('yes', $viewUserPerm->first()->pivot->allowed);
    }

    public function testInsertUserWithStatusPending()
    {
        $user = new User();
        $user->username = 'ironman';
        $user->user_password = Hash::make('ironman');
        $user->user_email = 'ironman@localhost.org';
        $user->user_firstname = 'Iron';
        $user->user_lastname = 'Man';
        $user->user_last_login = '2014-10-20 06:20:06';
        $user->user_ip = '10.10.0.14';
        $user->user_role_id = '3';
        $user->status = 'pending';
        $user->modified_by = '1';
        $user->created_at = '2014-10-20 06:30:06';
        $user->updated_at = '2014-10-20 06:31:06';
        $user->save();

        $expect = 6;
        $return = User::count();
        $this->assertSame($expect, $return);
    }

    public function testScopeActive()
    {
        $expect = 3;
        $return = User::active()->count();
        $this->assertSame($expect, $return);
    }

    public function testScopeBlocked()
    {
        $expect = 1;
        $return = User::blocked()->count();
        $this->assertSame($expect, $return);
    }

    public function testScopePending()
    {
        $expect = 1;
        $return = User::pending()->count();
        $this->assertSame($expect, $return);
    }

    public function testScopeDeleted()
    {
        $expect = 1;
        $return = User::blocked()->count();
        $this->assertSame($expect, $return);
    }

    public function testScopeMake_ironman_BecomeActive()
    {
        // Let's change the status to active
        User::where('user_email', 'ironman@localhost.org')
            ->first()
            ->makeActive()
            ->save();

        $expect = 'active';
        $return = User::where('user_email', 'ironman@localhost.org')->first()->status;
        $this->assertSame($expect, $return);

        // Number of active records should be increased by one
        $expect = 4;
        $return = User::active()->count();
        $this->assertSame($expect, $return);

        // Number of pending records should be decreased by one
        $expect = 0;
        $return = User::pending()->count();
        $this->assertSame($expect, $return);
    }

    public function testScopeMake_ironman_BecomeBlocked()
    {
        // Let's change the status to active
        User::where('user_email', 'ironman@localhost.org')
            ->first()
            ->makeBlocked()
            ->save();

        $expect = 'blocked';
        $return = User::where('user_email', 'ironman@localhost.org')->first()->status;
        $this->assertSame($expect, $return);

        // Number of active records should be decreased by one
        $expect = 3;
        $return = User::active()->count();
        $this->assertSame($expect, $return);

        // Number of blcked records should be increased by one
        $expect = 2;
        $return = User::blocked()->count();
        $this->assertSame($expect, $return);
    }

    public function testScopeMake_ironman_BecomeDeleted()
    {
        // Let's change the status to active
        User::where('user_email', 'ironman@localhost.org')
            ->first()
            ->delete();

        $expect = 'deleted';
        $return = User::where('user_email', 'ironman@localhost.org')->first()->status;
        $this->assertSame($expect, $return);

        // Number of blocked records should be decreased by one
        $expect = 1;
        $return = User::blocked()->count();
        $this->assertSame($expect, $return);

        // Number of deleted records should be increase by one
        $expect = 2;
        $return = User::withDeleted()->count();
        $this->assertSame($expect, $return);
    }

    public function testScopeExcludeDeleted()
    {
        $expect = 4;
        $return = User::excludeDeleted()->count();
        $this->assertSame($expect, $return);
    }

    public function testDestroyRecordNumber4()
    {
        User::destroy(4);

        // Should be 3, since destroy internally calls delete()
        // 'optimus', 'panther', and 'ironman'
        $expect = 3;
        $return = User::withDeleted()->count();
        $this->assertSame($expect, $return);
    }

    public function testForceDeleteRecord_ironman()
    {
        User::where('username', 'ironman')->first()->delete(TRUE);

        // Should be 2, since record ironman has been wiped from database
        $expect = 2;
        $return = User::withDeleted()->count();
        $this->assertSame($expect, $return);

        // Total record should be 5
        $expect = 5;
        $return = User::count();
        $this->assertSame($expect, $return);
    }
}
