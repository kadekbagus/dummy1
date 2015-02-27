<?php
/**
 * Seeder for User
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class MerchantDataSeeder extends Seeder
{
    public function run()
    {
        $passwordMerchant = 'merchant2015';
        $merchantUserData = [
            'user_id'           => 2,
            'username'          => 'merchant',
            'user_email'        => 'merchant@myorbit.com',
            'user_password'     => Hash::make($passwordMerchant),
            'user_firstname'    => 'Orbit',
            'user_lastname'     => 'Merchant',
            'status'            => 'active',
            'user_role_id'      => 4 // => Merchant Owner
        ];
        $passwordRetailer = 'retailer2015';
        $retailerUserData = [
            'user_id'           => 3,
            'username'          => 'retailer',
            'user_email'        => 'retailer@myorbit.com',
            'user_password'     => Hash::make($passwordRetailer),
            'user_firstname'    => 'Orbit',
            'user_lastname'     => 'Retailer',
            'status'            => 'active',
            'user_role_id'      => 5 // => Retailer Owner
        ];

        // ------- MERCHANT USER
        $this->command->info('Seeding merchant and retailer data...');
        DB::table('merchants')->truncate();
        User::unguard();

        $merchantUser = User::create($merchantUserData);
        $this->command->info(sprintf('    Create users record for merchant, username: %s.', $merchantUserData['username']));

        // Record for user_details table
        $merchantUserDetail = [
            'user_detail_id'    => 2,
            'user_id'           => 2
        ];
        UserDetail::unguard();
        UserDetail::create($merchantUserDetail);
        $this->command->info('    Create merchant record on user_details.');

        // Record for apikeys table
        $merchantUser->createApiKey();

        // ------- RETAILER USER
        $retailerUser = User::create($retailerUserData);
        $this->command->info(sprintf('    Create users record for retailer, username: %s.', $retailerUserData['username']));

        // Record for user_details table
        $retailerUserDetail = [
            'user_detail_id'    => 3,
            'user_id'           => 3
        ];
        UserDetail::unguard();
        UserDetail::create($retailerUserDetail);
        $this->command->info('    Create retailer record on user_details.');

        // Record for apikeys table
        $merchantUser->createApiKey();

        // Data for merchant
        $merchantData = [
            'merchant_id'   => 1,
            'omid'          => 'ORBIT-MERCHANT-01',
            'user_id'       => 2,
            'email'         => 'orbit-merchant@localhost.org',
            'name'          => 'Orbit Merchant',
            'description'   => 'Dummy merchant for Orbit test',
            'status'        => 'active',
            'modified_by'   => 0
        ];

        // Data for retailer
        $retailerData = [
            'merchant_id'   => 2,
            'omid'          => 'ORBIT-RETAILER-01',
            'user_id'       => 3,
            'email'         => 'orbit-retailer@localhost.org',
            'name'          => 'Orbit Retailer',
            'description'   => 'Dummy retailer for Orbit test',
            'status'        => 'active',
            'parent_id'     => 1,
            'modified_by'   => 0
        ];

        // ------- MERCHANT DATA
        Merchant::unguard();
        $merchant = Merchant::create($merchantData);
        $this->command->info(sprintf('    Create record on merchants table, name: %s.', $merchantData['name']));

        // ------- RETAILER DATA
        Retailer::unguard();
        $retailer = Retailer::create($retailerData);
        $this->command->info(sprintf('    Create record on retailers table, name: %s.', $retailerData['name']));

        $this->command->info('Merchant and retailer data seeded.');
    }
}
