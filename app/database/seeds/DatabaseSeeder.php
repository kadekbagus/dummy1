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
        try {
            $this->doRun();
        } catch(Exception $e)
        {
            $this->command->error($e);
        }
    }

    public function doRun()
    {
        Eloquent::unguard();


        DB::connection()->getPdo()->beginTransaction();

        $this->call('RoleTableSeeder');
        $this->call('PermissionTableSeeder');
        $this->call('PermissionRoleTableSeeder');
        $this->call('UserTableSeeder');
        $this->call('MerchantDataSeeder');
        $this->call('CountryTableSeeder');
        $this->call('PersonalInterestTableSeeder');
        $this->call('SettingTableSeeder');

        DB::connection()->getPdo()->commit();

        //$this->call('ProductSeeder');
    }
}
