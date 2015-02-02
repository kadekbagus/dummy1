<?php

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Eloquent::unguard();


        DB::connection()->getPdo()->beginTransaction();

        $this->call('RoleTableSeeder');
        $this->call('PermissionTableSeeder');
        $this->call('PermissionRoleTableSeeder');
        $this->call('UserTableSeeder');

        DB::connection()->getPdo()->commit();
    }
}
