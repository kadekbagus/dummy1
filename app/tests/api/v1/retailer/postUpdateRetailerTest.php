<?php
/**
 * Unit testing for RetailerAPIController::postUpdateRetailer() method.
 *
 * @author Tian <tian@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\OrbitShopAPI;

class postUpdateRetailerTest extends OrbitTestCase
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
        $user_detail_table = static::$dbPrefix . 'user_details';
        $role_table = static::$dbPrefix . 'roles';
        $permission_table = static::$dbPrefix . 'permissions';
        $permission_role_table = static::$dbPrefix . 'permission_role';
        $custom_permission_table = static::$dbPrefix . 'custom_permission';
        $merchant_table = static::$dbPrefix . 'merchants';
        $retailer_table = static::$dbPrefix . 'merchants';

        // Insert dummy data on apikeys
        DB::statement("INSERT INTO `{$apikey_table}`
                (`apikey_id`, `api_key`, `api_secret_key`, `user_id`, `status`, `created_at`, `updated_at`)
                VALUES
                (1, 'abc123', 'abc12345678910', '1', 'deleted', '2014-10-19 20:02:01', '2014-10-19 20:03:01'),
                (2, 'bcd234', 'bcd23456789010', '2', 'active', '2014-10-19 20:02:02', '2014-10-19 20:03:02'),
                (3, 'cde345', 'cde34567890100', '3', 'active', '2014-10-19 20:02:03', '2014-10-19 20:03:03'),
                (4, 'def123', 'def12345678901', '1', 'active', '2014-10-19 20:02:04', '2014-10-19 20:03:04'),
                (5, 'efg212', 'efg09876543212', '4', 'blocked', '2014-10-19 20:02:05', '2014-10-19 20:03:05'),
                (6, 'hij313', 'hijklmn0987623', '4', 'active', '2014-10-19 20:02:06', '2014-10-19 20:03:06'),
                (7, 'klm432', 'klm09876543211', '5', 'active', '2014-10-19 20:02:07', '2014-10-19 20:03:07')"
        );

        $password = array(
            'john'      => Hash::make('john'),
            'smith'     => Hash::make('smith'),
            'chuck'     => Hash::make('chuck'),
            'optimus'   => Hash::make('optimus'),
            'panther'   => Hash::make('panther'),
            'droopy'   => Hash::make('droopy')
        );

        // Insert dummy data on users
        DB::statement("INSERT INTO `{$user_table}`
                (`user_id`, `username`, `user_password`, `user_email`, `user_firstname`, `user_lastname`, `user_last_login`, `user_ip`, `user_role_id`, `status`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'john', '{$password['john']}', 'john@localhost.org', 'John', 'Doe', '2014-10-20 06:20:01', '10.10.0.11', '1', 'active', '1', '2014-10-20 06:30:01', '2014-10-20 06:31:01'),
                ('2', 'smith', '{$password['smith']}', 'smith@localhost.org', 'John', 'Smith', '2014-10-20 06:20:02', '10.10.0.12', '3', 'active', '1', '2014-10-20 06:30:02', '2014-10-20 06:31:02'),
                ('3', 'chuck', '{$password['chuck']}', 'chuck@localhost.org', 'Chuck', 'Norris', '2014-10-20 06:20:03', '10.10.0.13', '3', 'active', '1', '2014-10-20 06:30:03', '2014-10-20 06:31:03'),
                ('4', 'optimus', '{$password['optimus']}', 'optimus@localhost.org', 'Optimus', 'Prime', '2014-10-20 06:20:04', '10.10.0.13', '3', 'blocked', '1', '2014-10-20 06:30:04', '2014-10-20 06:31:04'),
                ('5', 'panther', '{$password['panther']}', 'panther@localhost.org', 'Pink', 'Panther', '2014-10-20 06:20:05', '10.10.0.13', '3', 'deleted', '1', '2014-10-20 06:30:05', '2014-10-20 06:31:05'),
                ('6', 'droopy', '{$password['droopy']}', 'droopy@localhost.org', 'Droopy', 'Dog', '2014-10-20 06:20:06', '10.10.0.14', '3', 'pending', '1', '2014-10-20 06:30:06', '2014-10-20 06:31:06')"
        );

        // Insert dummy data on user_details
        DB::statement("INSERT INTO `{$user_detail_table}`
                    (user_detail_id, user_id, merchant_id, merchant_acquired_date, address_line1, address_line2, address_line3, postal_code, city_id, city, province_id, province, country_id, country, currency, currency_symbol, birthdate, gender, relationship_status, phone, photo, number_visit_all_shop, amount_spent_all_shop, average_spent_per_month_all_shop, last_visit_any_shop, last_visit_shop_id, last_purchase_any_shop, last_purchase_shop_id, last_spent_any_shop, last_spent_shop_id, modified_by, created_at, updated_at)
                    VALUES
                    ('1', '1', '1', '2014-10-21 06:20:01', 'Jl. Raya Semer', 'Kerobokan', 'Near Airplane Statue', '60219', '1', 'Denpasar', '1', 'Bali', '62', 'Indonesia', 'IDR', 'Rp', '1980-04-02', 'm', 'single',       '081234567891', 'images/customer/01.png', '10', '8100000.00', '1100000.00', '2014-05-21 12:12:11', '1', '2014-10-16 12:12:12', '1', '1100000.00', '1', '1', '2014-10-11 06:20:01', '2014-10-11 06:20:01'),
                    ('2', '2', '2', '2014-10-21 06:20:02', 'Jl. Raya Semer2', 'Kerobokan2', 'Near Airplane Statue2', '60229', '2', 'Denpasar2', '2', 'Bali2', '62', 'Indonesia', 'IDR', 'Rp', '1980-04-02', 'm', 'single',  '081234567892', 'images/customer/02.png', '11', '9000000.00', '9200000.00', '2014-02-21 12:12:12', '2', '2014-10-17 12:12:12', '2', '1500000.00', '2', '1', '2014-10-12 06:20:01', '2014-10-12 06:20:02'),
                    ('3', '3', '5', '2014-10-21 06:20:03', 'Jl. Raya Semer3', 'Kerobokan3', 'Near Airplane Statue3', '60239', '3', 'Denpasar3', '3', 'Bali3', '62', 'Indonesia', 'EUR', '€', '1980-04-03', 'm', 'married',   '081234567893', 'images/customer/03.png', '12', '8300000.00', '5300000.00', '2014-01-21 12:12:13', '3', '2014-10-18 12:12:12', '3', '1400000.00', '3', '1', '2014-10-13 06:20:01', '2014-10-13 06:20:03'),
                    ('4', '4', '4', '2014-10-21 06:20:04', 'Jl. Raya Semer4', 'Kerobokan4', 'Near Airplane Statue4', '60249', '4', 'Denpasar4', '4', 'Bali4', '62', 'Indonesia', 'IDR', 'Rp', '1987-04-04', 'm', 'married',  '081234567894', 'images/customer/04.png', '13', '8400000.00', '1400000.00', '2014-10-21 12:12:14', '4', '2014-10-19 12:12:12', '4', '1300000.00', '4', '1', '2014-10-14 06:20:04', '2014-10-14 06:20:04'),
                    ('5', '5', '5', '2014-10-21 06:20:05', 'Jl. Raya Semer5', 'Kerobokan5', 'Near Airplane Statue5', '60259', '5', 'Denpasar5', '5', 'Bali5', '62', 'Indonesia', 'IDR', 'Rp', '1975-02-05', 'm', 'single',  '081234567895', 'images/customer/05.png', '14', '8500000.00', '1500000.00', '2014-10-29 12:12:15', '5', '2014-10-20 12:12:12', '5', '1200000.00', '5', '1', '2014-10-15 06:20:05', '2014-10-15 06:20:05'),
                    ('6', '6', '5', '2014-10-21 06:20:06', 'Orchard Road', 'Orchard6', 'Near Airplane Statue6', '60259', '6', 'Singapore6', '20', 'Singapore6', '61', 'Singapore', 'SGD', 'SG', '1987-02-05', 'm', 'single',  '081234567896', 'images/customer/06.png', '15', '8600000.00', '1500000.00', '2014-11-21 12:12:15', '5', '2014-10-20 12:12:12', '5', '1200000.00', '5', '1', '2014-10-15 06:20:05', '2014-10-15 06:20:05')"
        );

        // Insert dummy data on roles
        DB::statement("INSERT INTO `{$role_table}`
                (`role_id`, `role_name`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'Super Admin', '1', NOW(), NOW()),
                ('2', 'Guest', '1', NOW(), NOW()),
                ('3', 'Customer', '1', NOW(), NOW()),
                ('4', 'Merchant', '1', NOW(), NOW()),
                ('5', 'Retailer', '1', NOW(), NOW())"
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

        // Insert dummy merchants
        DB::statement("INSERT INTO `{$merchant_table}`
                (`merchant_id`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `city_id`, `city`, `country_id`, `country`, `phone`, `fax`, `start_date_activity`, `status`, `logo`, `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `object_type`, `parent_id`, `created_at`, `updated_at`, `modified_by`, `omid`, `orid`)
                VALUES
                ('1', '2', 'alfamer@localhost.org', 'Alfa Mer', 'Super market Alfa', 'Jl. Tunjungan 01', 'Komplek B1', 'Lantai 01', '10', 'Surabaya', '62', 'Indonesia', '031-7123456', '031-712344', '2012-01-02 01:01:01', 'active', 'merchants/logo/alfamer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Murah dan Tidak Hemat', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 'M01', ''),
                ('2', '3', 'indomer@localhost.org', 'Indo Mer', 'Super market Indo', 'Jl. Tunjungan 02', 'Komplek B2', 'Lantai 02', '10', 'Surabaya', '62', 'Indonesia', '031-8123456', '031-812344', '2012-02-02 01:01:02', 'active', 'merchants/logo/indomer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Harga Kurang Pas', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 'M02', ''),
                ('3', '2', 'mitra9@localhost.org', 'Mitra 9', 'Super market Bangunan', 'Jl. Tunjungan 03', 'Komplek B3', 'Lantai 03', '10', 'Surabaya', '62', 'Indonesia', '031-6123456', '031-612344', '2012-03-02 01:01:03', 'pending', 'merchants/logo/mitra9.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Belanja Bangunan Nyaman', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 'M03', ''),
                ('4', '1', 'keefce@localhost.org', 'Ke Ef Ce', 'Chicket Fast Food', 'Jl. Tunjungan 04', 'Komplek B4', 'Lantai 04', '10', 'Surabaya', '62', 'Indonesia', '031-5123456', '031-512344', '2012-04-02 01:01:04', 'blocked', 'merchants/logo/keefce1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Bukan Jagonya Ayam!', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 'M04', ''),
                ('5', '1', 'mekdi@localhost.org', 'Mek Di', 'Burger Fast Food', 'Jl. Tunjungan 05', 'Komplek B5', 'Lantai 05', '10', 'Surabaya', '62', 'Indonesia', '031-4123456', '031-412344', '2012-05-02 01:01:05', 'inactive', 'merchants/logo/mekdi1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'I\'m not lovit', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 'M05', ''),
                ('6', '1', 'setarbak@localhost.org', 'Setar Bak', 'Tempat Minum Kopi', 'Jl. Tunjungan 06', 'Komplek B6', 'Lantai 06', '10', 'Surabaya', '62', 'Indonesia', '031-3123456', '031-312344', '2012-06-02 01:01:06', 'deleted', 'merchants/logo/setarbak1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Coffee and TV', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 'M06', ''),
                ('7', '3', 'matabulan@localhost.org', 'Mata Bulan', 'Tempat Beli Baju', 'Jl. Tunjungan 07', 'Komplek B7', 'Lantai 07', '10', 'Surabaya', '62', 'Indonesia', '031-2123456', '031-212344', '2012-07-02 01:01:06', 'inactive', 'merchants/logo/matabulan.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Sale Everyday', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 'M07', ''),
                ('8', '8', 'dummy@localhost.org', 'Dummy Object', 'Doom', 'Jl. Tunjungan 08', 'Komplek B8', 'Lantai 08', '10', 'Surabaya', '62', 'Indonesia', '031-1123456', '031-112344', '2012-08-02 01:01:08', 'active', 'merchants/logo/dummy1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'dummy', NULL, NOW(), NOW(), 1, 'M08', ''),
                ('9', '4', 'alfagubeng@localhost.org', 'Alfa Mer Gubeng Pojok', 'Alfa Mer which near Gubeng Station Surabaya', 'Jl. Gubeng 09', 'Komplek B9', 'Lantai 09', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-09-02 01:01:09', 'active', 'merchants/logo/alfamer-gubeng.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 2, NOW(), NOW(), 1, 'M09', '')"
        );

        // Insert dummy retailers
        DB::statement("INSERT INTO `{$retailer_table}`
                    (`merchant_id`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `city_id`, `city`, `country_id`, `country`, `phone`, `fax`, `start_date_activity`, `status`, `logo`, `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `object_type`, `parent_id`, `created_at`, `updated_at`, `modified_by`, `omid`, `orid`)
                    VALUES
                    ('10', '2', 'alfamer@localhost.org', 'Alfa Mer AAA', 'Super market Alfa', 'Jl. Tunjungan 01', 'Komplek B1', 'Lantai 01', '10', 'Surabaya', '62', 'Indonesia', '031-7123456', '031-712344', '2012-01-02 01:01:01', 'active', 'merchants/logo/alfamer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Murah dan Tidak Hemat', 'yes', 'retailer', 1, NOW(), NOW(), 1, '', 'R01'),
                    ('11', '3', 'indomer@localhost.org', 'Indo Mer BBB', 'Super market Indo', 'Jl. Tunjungan 02', 'Komplek B2', 'Lantai 02', '10', 'Surabaya', '62', 'Indonesia', '031-8123456', '031-812344', '2012-02-02 01:01:02', 'active', 'merchants/logo/indomer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Harga Kurang Pas', 'yes', 'retailer', 1, NOW(), NOW(), 1, '', 'R02'),
                    ('12', '2', 'mitra9@localhost.org', 'Mitra 9 CCC', 'Super market Bangunan', 'Jl. Tunjungan 03', 'Komplek B3', 'Lantai 03', '10', 'Surabaya', '62', 'Indonesia', '031-6123456', '031-612344', '2012-03-02 01:01:03', 'pending', 'merchants/logo/mitra9.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Belanja Bangunan Nyaman', 'yes', 'retailer', 2, NOW(), NOW(), 1, '', 'R03'),
                    ('13', '1', 'keefce@localhost.org', 'Ke Ef Ce DDD', 'Chicket Fast Food', 'Jl. Tunjungan 04', 'Komplek B4', 'Lantai 04', '10', 'Surabaya', '62', 'Indonesia', '031-5123456', '031-512344', '2012-04-02 01:01:04', 'blocked', 'merchants/logo/keefce1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Bukan Jagonya Ayam!', 'yes', 'retailer', 2, NOW(), NOW(), 1, '', 'R04'),
                    ('14', '1', 'mekdi@localhost.org', 'Mek Di EEE', 'Burger Fast Food', 'Jl. Tunjungan 05', 'Komplek B5', 'Lantai 05', '10', 'Surabaya', '62', 'Indonesia', '031-4123456', '031-412344', '2012-05-02 01:01:05', 'inactive', 'merchants/logo/mekdi1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'I\'m not lovit', 'yes', 'retailer', 2, NOW(), NOW(), 1, '', 'R05'),
                    ('15', '1', 'setarbak@localhost.org', 'Setar Bak FFF', 'Tempat Minum Kopi', 'Jl. Tunjungan 06', 'Komplek B6', 'Lantai 06', '10', 'Surabaya', '62', 'Indonesia', '031-3123456', '031-312344', '2012-06-02 01:01:06', 'deleted', 'merchants/logo/setarbak1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Coffee and TV', 'yes', 'retailer', 3, NOW(), NOW(), 1, '', 'R06'),
                    ('16', '3', 'matabulan@localhost.org', 'Mata Bulan GGG', 'Tempat Beli Baju', 'Jl. Tunjungan 07', 'Komplek B7', 'Lantai 07', '10', 'Surabaya', '62', 'Indonesia', '031-2123456', '031-212344', '2012-07-02 01:01:06', 'inactive', 'merchants/logo/matabulan.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Sale Everyday', 'yes', 'retailer', 4, NOW(), NOW(), 1, '', 'R07'),
                    ('17', '8', 'dummy@localhost.org', 'Dummy Object HHH', 'Doom', 'Jl. Tunjungan 08', 'Komplek B8', 'Lantai 08', '10', 'Surabaya', '62', 'Indonesia', '031-1123456', '031-112344', '2012-08-02 01:01:08', 'active', 'merchants/logo/dummy1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'dummy', 5, NOW(), NOW(), 1, '', 'R08'),
                    ('18', '4', 'alfagubeng@localhost.org', 'Alfa Mer', 'Alfa Mer Pusat', 'Jl. Gubeng 09', 'Komplek B9', 'Lantai 09', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-09-02 01:01:09', 'active', 'merchants/logo/alfamer-gubeng.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 6, NOW(), NOW(), 1, '', 'R09')"
        );
    }

    /**
     * Clear all data that has been inserted.
     */
    public static function truncateData()
    {
        $apikey_table = static::$dbPrefix . 'apikeys';
        $user_table = static::$dbPrefix . 'users';
        $user_detail_table = static::$dbPrefix . 'user_details';
        $role_table = static::$dbPrefix . 'roles';
        $permission_table = static::$dbPrefix . 'permissions';
        $permission_role_table = static::$dbPrefix . 'permission_role';
        $custom_permission_table = static::$dbPrefix . 'custom_permission';
        $merchant_table = static::$dbPrefix . 'merchants';
        DB::unprepared("TRUNCATE `{$apikey_table}`;
                        TRUNCATE `{$user_table}`;
                        TRUNCATE `{$user_detail_table}`;
                        TRUNCATE `{$role_table}`;
                        TRUNCATE `{$custom_permission_table}`;
                        TRUNCATE `{$permission_role_table}`;
                        TRUNCATE `{$permission_table}`;
                        TRUNCATE `{$merchant_table}`");
    }

    public function tearDown()
    {
        unset($_GET);
        unset($_POST);
        $_GET = array();
        $_POST = array();

        unset($_SERVER['HTTP_X_ORBIT_SIGNATURE'],
              $_SERVER['REQUEST_METHOD'],
              $_SERVER['REQUEST_URI'],
              $_SERVER['REMOTE_ADDR']);

        // Make sure we always get a fresh instance of user
        $apikeys = array(
            'abc123',
            'bcd234',
            'cde345',
            'def123',
            'efg212',
            'hij313',
            'klm432',
        );

        foreach ($apikeys as $key) {
            OrbitShopAPI::clearLookupCache($key);
        }

        // Clear every event dispatcher so we get no queue event on each
        // test
        $events = array(
            'orbit.retailer.postupdateretailer.before.auth',
            'orbit.retailer.postupdateretailer.after.auth',
            'orbit.retailer.postupdateretailer.before.authz',
            'orbit.retailer.postupdateretailer.authz.notallowed',
            'orbit.retailer.postupdateretailer.after.authz',
            'orbit.retailer.postupdateretailer.before.validation',
            'orbit.retailer.postupdateretailer.after.validation',
            'orbit.retailer.postupdateretailer.before.save',
            'orbit.retailer.postupdateretailer.after.save',
            'orbit.retailer.postupdateretailer.after.commit',
            'orbit.retailer.postupdateretailer.access.forbidden',
            'orbit.retailer.postupdateretailer.invalid.arguments',
            'orbit.retailer.postupdateretailer.general.exception',
            'orbit.retailer.postupdateretailer.before.render'
        );
        foreach ($events as $event) {
            Event::forget($event);
        }
    }

    public function testObjectInstance()
    {
        $ctl = new RetailerAPIController();
        $this->assertInstanceOf('RetailerAPIController', $ctl);
    }

    public function testNoAuthData_POST_api_v1_retailer_update()
    {
        $url = '/api/v1/retailer/update';

        $data = new stdclass();
        $data->code = Status::CLIENT_ID_NOT_FOUND;
        $data->status = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidSignature_POST_api_v1_retailer_update()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = 'dummy-signature';

        $data = new stdclass();
        $data->code = Status::INVALID_SIGNATURE;
        $data->status = 'error';
        $data->message = Status::INVALID_SIGNATURE_MSG;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testSignatureExpire_POST_api_v1_retailer_update()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time() - 3600;  // an hour ago

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $data = new stdclass();
        $data->code = Status::REQUEST_EXPIRED;
        $data->status = 'error';
        $data->message = Status::REQUEST_EXPIRED_MSG;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testAccessForbidden_POST_api_v1_retailer_update()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // Error message when access is forbidden
        $updateRetailerLang = Lang::get('validation.orbit.actionlist.update_retailer');
        $message = Lang::get('validation.orbit.access.forbidden',
                             array('action' => $updateRetailerLang));

        $data = new stdclass();
        $data->code = Status::ACCESS_DENIED;
        $data->status = 'error';
        $data->message = $message;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testMissingRetailerId_POST_api_v1_retailer_update()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // Add new permission name 'update_retailer'
        $chuck = User::find(3);
        $permission = new Permission();
        $permission->permission_name = 'update_retailer';
        $permission->save();

        $chuck->permissions()->attach($permission->permission_id, array('allowed' => 'yes'));

        $message = Lang::get('validation.required', array('attribute' => 'retailer id'));
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testRetailerIdNotNumeric_POST_api_v1_retailer_update()
    {
        // Data to be post
        $_POST['retailer_id'] = 'foo';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.numeric', array('attribute' => 'retailer id'));
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testRetailerIdNotExists_POST_api_v1_retailer_update()
    {
        // Data to be post
        $_POST['retailer_id'] = 99;
        $_POST['user_id'] = 3;
        $_POST['email'] = 'george@localhost.org';
        $_POST['name'] = 'update retailer id not exists';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.orbit.empty.retailer');
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testUserIdNotNumeric_POST_api_v1_retailer_update()
    {
        // Data to be post
        $_POST['retailer_id'] = 10;
        $_POST['user_id'] = 'foo';
        $_POST['email'] = 'george@localhost.org';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.numeric', array('attribute' => 'user id'));
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testUserIdNotExists_POST_api_v1_retailer_update()
    {
        // Data to be post
        $_POST['retailer_id'] = 10;
        $_POST['user_id'] = 99;
        $_POST['email'] = 'george@localhost.org';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.orbit.empty.user');
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidEmailFormat_POST_api_v1_retailer_update()
    {
        // Data to be post
        $_POST['retailer_id'] = 10;
        $_POST['user_id'] = 3;
        $_POST['email'] = 'wrong-format@localhost';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = Lang::get('validation.email', array('attribute' => 'email'));
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testStatusNotExists_POST_api_v1_retailer_update()
    {
        // Data to be post
        $_POST['retailer_id'] = 10;
        $_POST['user_id'] = 3;
        $_POST['email'] = 'george@localhost.org';
        $_POST['name'] = 'test status not exists';
        $_POST['status'] = 'dummy';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.orbit.empty.retailer_status');
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testEmailAlreadyExists_POST_api_v1_retailer_update()
    {
        // Data to be post
        $_POST['retailer_id'] = 10;
        $_POST['user_id'] = 3;
        $_POST['email'] = 'indomer@localhost.org';
        $_POST['name'] = 'Alfa Beta';
        $_POST['status'] = 'active';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.orbit.exists.email');
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testORIDAlreadyExists_POST_api_v1_retailer_update()
    {
        // Data to be post
        $_POST['retailer_id'] = 10;
        $_POST['user_id'] = 3;
        $_POST['email'] = 'alfabeta@localhost.org';
        $_POST['name'] = 'test omid already exists';
        $_POST['orid'] = 'R02';
        $_POST['status'] = 'active';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.orbit.exists.orid');
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testReqOK_POST_api_v1_retailer_update()
    {
        // Number of retailer account before this operation
        $numBefore = Retailer::count();

        // Data to be post
        $_POST['retailer_id'] = 16;
        $_POST['user_id'] = 2;
        $_POST['email'] = 'test@retailer.update';
        $_POST['name'] = 'test request ok: retailer update';
        $_POST['orid'] = 'R011';
        $_POST['status'] = 'pending';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $return = $this->call('POST', $url)->getContent();

        $response = json_decode($return);
        $this->assertSame(0, (int)$response->code);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('2', (string)$response->data->user_id);
        $this->assertSame('test@retailer.update', $response->data->email);
        $this->assertSame('test request ok: retailer update', $response->data->name);
        $this->assertSame('pending', $response->data->status);
        $this->assertSame('3', (string)$response->data->modified_by);
        $this->assertSame('retailer', (string)$response->data->object_type);

        $numAfter = Retailer::count();
        $this->assertSame($numBefore, $numAfter);
    }

    public function testSavedThenRollback_POST_api_v1_retailer_update()
    {
        // Register an event on 'orbit.retailer.postupdateretailer.after.save'
        // and throw some exception so the data that has been saved
        // does not commited
        Event::listen('orbit.retailer.postupdateretailer.after.save', function($controller, $retailer)
        {
            throw new Exception('This is bad bro!', 99);
        });

        // Number of retailer account before this operation
        $numBefore = Retailer::count();

        // Data to be post
        $_POST['retailer_id'] = 10;
        $_POST['user_id'] = 3;
        $_POST['email'] = 'test-rollback@retailer.update';
        $_POST['name'] = 'test saved then rollback';
        $_POST['orid'] = 'R012';
        $_POST['status'] = 'pending';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $data = new stdclass();
        $data->code = 99;
        $data->status = 'error';
        $data->message = 'This is bad bro!';
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);

        // The data should remain the same as the old one
        $retailer = Retailer::find(10);
        $this->assertSame('2', (string)$retailer->user_id);
        $this->assertSame('alfamer@localhost.org', $retailer->email);
        $this->assertSame('Alfa Mer AAA', $retailer->name);
        $this->assertSame('Super market Alfa', $retailer->description);
        $this->assertSame('active', (string)$retailer->status);
        $this->assertSame('retailer', (string)$retailer->object_type);
        $this->assertSame('1', (string)$retailer->modified_by);

        $numAfter = Retailer::count();
        $this->assertSame($numBefore, $numAfter);
    }
}