<?php
/**
 * Unit testing for UserAPIController::getSearchUser() method.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @specs:
 * The default return value for this API are:
 * {
 *      "code": CODE,
 *      "status": STATUS,
 *      "message": MESSAGE,
 *      "data":
 *      {
 *          "total_records": NUMBER_OF_TOTAL_RECORDS,
 *          "returned_records": NUMBER_OF_RETURNED_RECORDS,
 *          "records": []
 *      }
 * }
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\OrbitShopAPI;

class getSearchUserTest extends OrbitTestCase
{

    /** @var array $userData user data for use in SQL queries  */
    private static $userData;

    /** @var array $userDetailsData user details data for use in SQL queries  */
    private static $userDetailsData;

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
            'droopy'    => Hash::make('droopy'),
            'catwoman'  => Hash::make('catwoman'),
        );

        static::$userData = array(
            'john' => array(
                'user_id' => '1',
                'username' => 'john',
                'user_password' => $password['john'],
                'user_email' => 'john@localhost.org',
                'user_firstname' => 'John',
                'user_lastname' => 'Doe',
                'user_last_login' => '2014-10-20 06:20:01',
                'user_ip' => '10.10.0.11',
                'user_role_id' => '1',
                'status' => 'active',
                'modified_by' => '1',
                'created_at' => '2014-10-20 06:30:01',
                'updated_at' => '2014-10-20 06:31:01',
            ),
            'smith' => array(
                'user_id' => '2',
                'username' => 'smith',
                'user_password' => $password['smith'],
                'user_email' => 'smith@localhost.org',
                'user_firstname' => 'John',
                'user_lastname' => 'Smith',
                'user_last_login' => '2014-10-20 06:20:02',
                'user_ip' => '10.10.0.12',
                'user_role_id' => '3',
                'status' => 'active',
                'modified_by' => '1',
                'created_at' => '2014-10-20 06:30:02',
                'updated_at' => '2014-10-20 06:31:02',
            ),
            'chuck' => array(
                'user_id' => '3',
                'username' => 'chuck',
                'user_password' => $password['smith'],
                'user_email' => 'chuck@localhost.org',
                'user_firstname' => 'Chuck',
                'user_lastname' => 'Norris',
                'user_last_login' => '2014-10-20 06:20:03',
                'user_ip' => '10.10.0.13',
                'user_role_id' => '3',
                'status' => 'active',
                'modified_by' => '1',
                'created_at' => '2014-10-20 06:30:03',
                'updated_at' => '2014-10-20 06:31:03',
            ),
            'optimus' => array(
                'user_id' => '4',
                'username' => 'optimus',
                'user_password' => $password['optimus'],
                'user_email' => 'optimus@localhost.org',
                'user_firstname' => 'Optimus',
                'user_lastname' => 'Prime',
                'user_last_login' => '2014-10-20 06:20:04',
                'user_ip' => '10.10.0.13',
                'user_role_id' => '3',
                'status' => 'blocked',
                'modified_by' => '1',
                'created_at' => '2014-10-20 06:30:04',
                'updated_at' => '2014-10-20 06:31:04',
            ),
            'panther' => array(
                'user_id' => '5',
                'username' => 'panther',
                'user_password' => $password['panther'],
                'user_email' => 'panther@localhost.org',
                'user_firstname' => 'Pink',
                'user_lastname' => 'Panther',
                'user_last_login' => '2014-10-20 06:20:05',
                'user_ip' => '10.10.0.13',
                'user_role_id' => '3',
                'status' => 'deleted',
                'modified_by' => '1',
                'created_at' => '2014-10-20 06:30:05',
                'updated_at' => '2014-10-20 06:31:05',
            ),
            'droopy' => array(
                'user_id' => '6',
                'username' => 'droopy',
                'user_password' => $password['droopy'],
                'user_email' => 'droopy@localhost.org',
                'user_firstname' => 'Droopy',
                'user_lastname' => 'Dog',
                'user_last_login' => '2014-10-20 06:20:06',
                'user_ip' => '10.10.0.14',
                'user_role_id' => '3',
                'status' => 'pending',
                'modified_by' => '1',
                'created_at' => '2014-10-20 06:30:06',
                'updated_at' => '2014-10-20 06:31:06',
            ),
            'catwoman' => array(
                'user_id' => '7',
                'username' => 'catwoman',
                'user_password' => $password['catwoman'],
                'user_email' => 'catwoman@localhost.org',
                'user_firstname' => 'Cat',
                'user_lastname' => 'Woman',
                'user_last_login' => '2014-10-20 06:20:07',
                'user_ip' => '10.10.0.17',
                'user_role_id' => '4',
                'status' => 'active',
                'modified_by' => '1',
                'created_at' => '2014-10-20 06:30:07',
                'updated_at' => '2014-10-20 06:31:07',
            ),
        );

        // Insert dummy data on users
        foreach (static::$userData as $user_data) {
            DB::statement("INSERT INTO `{$user_table}`
                (`user_id`, `username`, `user_password`, `user_email`, `user_firstname`, `user_lastname`, `user_last_login`, `user_ip`, `user_role_id`, `status`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                (:user_id, :username, :user_password, :user_email, :user_firstname, :user_lastname, :user_last_login, :user_ip, :user_role_id, :status, :modified_by, :created_at, :updated_at)",
                $user_data);
        }

        static::$userDetailsData = array(
            'john' => array(
                'user_detail_id' => '1',
                'user_id' => '1',
                'merchant_id' => '1',
                'merchant_acquired_date' => '2014-10-21 06:20:01',
                'address_line1' => 'Jl. Raya Semer',
                'address_line2' => 'Kerobokan',
                'address_line3' => 'Near Airplane Statue',
                'postal_code' => '60219',
                'city_id' => '1',
                'city' => 'Denpasar',
                'province_id' => '1',
                'province' => 'Bali',
                'country_id' => '62',
                'country' => 'Indonesia',
                'currency' => 'IDR',
                'currency_symbol' => 'Rp',
                'birthdate' => '1980-04-02',
                'gender' => 'm',
                'relationship_status' => 'single',
                'phone' => '081234567891',
                'photo' => 'images/customer/01.png',
                'number_visit_all_shop' => '10',
                'amount_spent_all_shop' => '8100000.00',
                'average_spent_per_month_all_shop' => '1100000.00',
                'last_visit_any_shop' => '2014-05-21 12:12:11',
                'last_visit_shop_id' => '1',
                'last_purchase_any_shop' => '2014-10-16 12:12:12',
                'last_purchase_shop_id' => '1',
                'last_spent_any_shop' => '1100000.00',
                'last_spent_shop_id' => '1',
                'modified_by' => '1',
                'created_at' => '2014-10-11 06:20:01',
                'updated_at' => '2014-10-11 06:20:01',
            ),
            'smith' => array(
                'user_detail_id' => '2',
                'user_id' => '2',
                'merchant_id' => '2',
                'merchant_acquired_date' => '2014-10-21 06:20:02',
                'address_line1' => 'Jl. Raya Semer2',
                'address_line2' => 'Kerobokan2',
                'address_line3' => 'Near Airplane Statue2',
                'postal_code' => '60229',
                'city_id' => '2',
                'city' => 'Denpasar2',
                'province_id' => '2',
                'province' => 'Bali2',
                'country_id' => '62',
                'country' => 'Indonesia',
                'currency' => 'IDR',
                'currency_symbol' => 'Rp',
                'birthdate' => '1980-04-02',
                'gender' => 'm',
                'relationship_status' => 'single',
                'phone' => '081234567892',
                'photo' => 'images/customer/02.png',
                'number_visit_all_shop' => '11',
                'amount_spent_all_shop' => '9000000.00',
                'average_spent_per_month_all_shop' => '9200000.00',
                'last_visit_any_shop' => '2014-02-21 12:12:12',
                'last_visit_shop_id' => '2',
                'last_purchase_any_shop' => '2014-10-17 12:12:12',
                'last_purchase_shop_id' => '2',
                'last_spent_any_shop' => '1500000.00',
                'last_spent_shop_id' => '2',
                'modified_by' => '1',
                'created_at' => '2014-10-12 06:20:01',
                'updated_at' => '2014-10-12 06:20:02',
            ),
            'chuck' => array(
                'user_detail_id' => '3',
                'user_id' => '3',
                'merchant_id' => '5',
                'merchant_acquired_date' => '2014-10-21 06:20:03',
                'address_line1' => 'Jl. Raya Semer3',
                'address_line2' => 'Kerobokan3',
                'address_line3' => 'Near Airplane Statue3',
                'postal_code' => '60239',
                'city_id' => '3',
                'city' => 'Denpasar3',
                'province_id' => '3',
                'province' => 'Bali3',
                'country_id' => '62',
                'country' => 'Indonesia',
                'currency' => 'EUR',
                'currency_symbol' => '€',
                'birthdate' => '1980-04-03',
                'gender' => 'm',
                'relationship_status' => 'married',
                'phone' => '081234567893',
                'photo' => 'images/customer/03.png',
                'number_visit_all_shop' => '12',
                'amount_spent_all_shop' => '8300000.00',
                'average_spent_per_month_all_shop' => '5300000.00',
                'last_visit_any_shop' => '2014-01-21 12:12:13',
                'last_visit_shop_id' => '3',
                'last_purchase_any_shop' => '2014-10-18 12:12:12',
                'last_purchase_shop_id' => '3',
                'last_spent_any_shop' => '1400000.00',
                'last_spent_shop_id' => '3',
                'modified_by' => '1',
                'created_at' => '2014-10-13 06:20:01',
                'updated_at' => '2014-10-13 06:20:03',
            ),
            'optimus' => array(
                'user_detail_id' => '4',
                'user_id' => '4',
                'merchant_id' => '4',
                'merchant_acquired_date' => '2014-10-21 06:20:04',
                'address_line1' => 'Jl. Raya Semer4',
                'address_line2' => 'Kerobokan4',
                'address_line3' => 'Near Airplane Statue4',
                'postal_code' => '60249',
                'city_id' => '4',
                'city' => 'Denpasar4',
                'province_id' => '4',
                'province' => 'Bali4',
                'country_id' => '62',
                'country' => 'Indonesia',
                'currency' => 'IDR',
                'currency_symbol' => 'Rp',
                'birthdate' => '1987-04-04',
                'gender' => 'm',
                'relationship_status' => 'married',
                'phone' => '081234567894',
                'photo' => 'images/customer/04.png',
                'number_visit_all_shop' => '13',
                'amount_spent_all_shop' => '8400000.00',
                'average_spent_per_month_all_shop' => '1400000.00',
                'last_visit_any_shop' => '2014-10-21 12:12:14',
                'last_visit_shop_id' => '4',
                'last_purchase_any_shop' => '2014-10-19 12:12:12',
                'last_purchase_shop_id' => '4',
                'last_spent_any_shop' => '1300000.00',
                'last_spent_shop_id' => '4',
                'modified_by' => '1',
                'created_at' => '2014-10-14 06:20:04',
                'updated_at' => '2014-10-14 06:20:04',
            ),
            'panther' => array(
                'user_detail_id' => '5',
                'user_id' => '5',
                'merchant_id' => '5',
                'merchant_acquired_date' => '2014-10-21 06:20:05',
                'address_line1' => 'Jl. Raya Semer5',
                'address_line2' => 'Kerobokan5',
                'address_line3' => 'Near Airplane Statue5',
                'postal_code' => '60259',
                'city_id' => '5',
                'city' => 'Denpasar5',
                'province_id' => '5',
                'province' => 'Bali5',
                'country_id' => '62',
                'country' => 'Indonesia',
                'currency' => 'IDR',
                'currency_symbol' => 'Rp',
                'birthdate' => '1975-02-05',
                'gender' => 'm',
                'relationship_status' => 'single',
                'phone' => '081234567895',
                'photo' => 'images/customer/05.png',
                'number_visit_all_shop' => '14',
                'amount_spent_all_shop' => '8500000.00',
                'average_spent_per_month_all_shop' => '1500000.00',
                'last_visit_any_shop' => '2014-10-29 12:12:15',
                'last_visit_shop_id' => '5',
                'last_purchase_any_shop' => '2014-10-20 12:12:12',
                'last_purchase_shop_id' => '5',
                'last_spent_any_shop' => '1200000.00',
                'last_spent_shop_id' => '5',
                'modified_by' => '1',
                'created_at' => '2014-10-15 06:20:05',
                'updated_at' => '2014-10-15 06:20:05',
            ),
            'droopy' => array(
                'user_detail_id' => '6',
                'user_id' => '6',
                'merchant_id' => '5',
                'merchant_acquired_date' => '2014-10-21 06:20:06',
                'address_line1' => 'Orchard Road',
                'address_line2' => 'Orchard6',
                'address_line3' => 'Near Airplane Statue6',
                'postal_code' => '60259',
                'city_id' => '6',
                'city' => 'Singapore6',
                'province_id' => '20',
                'province' => 'Singapore6',
                'country_id' => '61',
                'country' => 'Singapore',
                'currency' => 'SGD',
                'currency_symbol' => 'SG',
                'birthdate' => '1987-02-05',
                'gender' => 'm',
                'relationship_status' => 'single',
                'phone' => '081234567896',
                'photo' => 'images/customer/06.png',
                'number_visit_all_shop' => '15',
                'amount_spent_all_shop' => '8600000.00',
                'average_spent_per_month_all_shop' => '1500000.00',
                'last_visit_any_shop' => '2014-11-21 12:12:15',
                'last_visit_shop_id' => '5',
                'last_purchase_any_shop' => '2014-10-20 12:12:12',
                'last_purchase_shop_id' => '5',
                'last_spent_any_shop' => '1200000.00',
                'last_spent_shop_id' => '5',
                'modified_by' => '1',
                'created_at' => '2014-10-15 06:20:05',
                'updated_at' => '2014-10-15 06:20:05',
            ),
            'catwoman' => array(
                'user_detail_id' => '7',
                'user_id' => '7',
                'merchant_id' => '10',
                'merchant_acquired_date' => '2014-10-21 06:20:06',
                'address_line1' => 'Jl. Pahlawan7',
                'address_line2' => 'Gubeng7',
                'address_line3' => 'Sebelah Tugu Pahlawan7',
                'postal_code' => '60259',
                'city_id' => '7',
                'city' => 'Surabaya7',
                'province_id' => '17',
                'province' => 'Jawa Timur',
                'country_id' => '62',
                'country' => 'Indonesia',
                'currency' => 'IDR',
                'currency_symbol' => 'Rp',
                'birthdate' => '1980-10-05',
                'gender' => 'f',
                'relationship_status' => 'single',
                'phone' => '081234567897',
                'photo' => 'images/customer/07.png',
                'number_visit_all_shop' => '20',
                'amount_spent_all_shop' => '8700000.00',
                'average_spent_per_month_all_shop' => '1500000.00',
                'last_visit_any_shop' => '2014-08-21 12:12:15',
                'last_visit_shop_id' => '5',
                'last_purchase_any_shop' => '2014-10-20 12:12:12',
                'last_purchase_shop_id' => '5',
                'last_spent_any_shop' => '1200000.00',
                'last_spent_shop_id' => '5',
                'modified_by' => '1',
                'created_at' => '2014-10-15 06:20:05',
                'updated_at' => '2014-10-15 06:20:05',
            )
        );

        // Insert dummy data on user_details
        foreach (static::$userDetailsData as $user_details_data) {
            DB::statement("INSERT INTO `{$user_detail_table}`
                (user_detail_id, user_id, merchant_id, merchant_acquired_date, address_line1, address_line2, address_line3, postal_code, city_id, city, province_id, province, country_id, country, currency, currency_symbol, birthdate, gender, relationship_status, phone, photo, number_visit_all_shop, amount_spent_all_shop, average_spent_per_month_all_shop, last_visit_any_shop, last_visit_shop_id, last_purchase_any_shop, last_purchase_shop_id, last_spent_any_shop, last_spent_shop_id, modified_by, created_at, updated_at)
                VALUES
                (:user_detail_id, :user_id, :merchant_id, :merchant_acquired_date, :address_line1, :address_line2, :address_line3, :postal_code, :city_id, :city, :province_id, :province, :country_id, :country, :currency, :currency_symbol, :birthdate, :gender, :relationship_status, :phone, :photo, :number_visit_all_shop, :amount_spent_all_shop, :average_spent_per_month_all_shop, :last_visit_any_shop, :last_visit_shop_id, :last_purchase_any_shop, :last_purchase_shop_id, :last_spent_any_shop, :last_spent_shop_id, :modified_by, :created_at, :updated_at)",
                $user_details_data);
        }


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
                ('6', '3', '5', 'no', NOW(), NOW()),
                ('7', '1', '2', 'yes', NOW(), NOW())"
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
        DB::unprepared("TRUNCATE `{$apikey_table}`;
                        TRUNCATE `{$user_table}`;
                        TRUNCATE `{$user_detail_table}`;
                        TRUNCATE `{$role_table}`;
                        TRUNCATE `{$custom_permission_table}`;
                        TRUNCATE `{$permission_role_table}`;
                        TRUNCATE `{$permission_table}`");
    }

    public function tearDown()
    {
        unset($_GET);
        unset($_POST);
        $_GET = array();
        $_POST = array();

        unset($_SERVER['HTTP_X_ORBIT_SIGNATURE'],
              $_SERVER['REQUEST_METHOD'],
              $_SERVER['REQUEST_URI']
        );

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
            'orbit.user.postupdateuser.before.auth',
            'orbit.user.postupdateuser.after.auth',
            'orbit.user.postupdateuser.before.authz',
            'orbit.user.postupdateuser.authz.notallowed',
            'orbit.user.postupdateuser.after.authz',
            'orbit.user.postupdateuser.before.validation',
            'orbit.user.postupdateuser.after.validation',
            'orbit.user.postupdateuser.access.forbidden',
            'orbit.user.postupdateuser.invalid.arguments',
            'orbit.user.postupdateuser.general.exception',
            'orbit.user.postupdateuser.before.render'
        );
        foreach ($events as $event) {
            Event::forget($event);
        }
    }

    public function testObjectInstance()
    {
        $ctl = new UserAPIController();
        $this->assertInstanceOf('UserAPIController', $ctl);
    }

    public function testNoAuthData_GET_api_v1_user_search()
    {
        $url = '/api/v1/user/search';

        $data = new stdclass();
        $data->code = Status::CLIENT_ID_NOT_FOUND;
        $data->status = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidSignature_GET_api_v1_user_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = 'dummy-signature';

        $data = new stdclass();
        $data->code = Status::INVALID_SIGNATURE;
        $data->status = 'error';
        $data->message = Status::INVALID_SIGNATURE_MSG;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testSignatureExpire_GET_api_v1_user_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time() - 3600;  // an hour ago

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $data = new stdclass();
        $data->code = Status::REQUEST_EXPIRED;
        $data->status = 'error';
        $data->message = Status::REQUEST_EXPIRED_MSG;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testAccessForbidden_GET_api_v1_user_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // Error message when access is forbidden
        $viewUserLang = Lang::get('validation.orbit.actionlist.view_user');
        $message = Lang::get('validation.orbit.access.forbidden',
                             array('action' => $viewUserLang));

        $data = new stdclass();
        $data->code = Status::ACCESS_DENIED;
        $data->status = 'error';
        $data->message = $message;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);

        // Add new permission name 'view_user'
        $chuck = User::find(3);
        $permission = new Permission();
        $permission->permission_name = 'view_user';
        $permission->save();

        $chuck->permissions()->attach($permission->permission_id, array('allowed' => 'yes'));
    }

    public function testOK_NoArgumentGiven_GET_api_v1_user_search()
    {
        // Data
        // No argument given at all, show all users
        // It should read from config named 'orbit.pagination.user.max_record'
        // It should fallback to whatever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.user.max_record', $max_record);
        Config::set('orbit.pagination.user.per_page', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 6 and returned records 2
        $this->assertSame(6, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array('catwoman', 'chuck');
        // It is ordered by first name by default so 1) chuck 2) droopy
        foreach ($response->data->records as $index=>$return)
        {
            $expected_user = static::$userData[$expect[$index]];
            $this->assertSame($expected_user['user_id'], (string)$return->user_id);
            $this->assertSame($expected_user['username'], $return->username);
            $this->assertSame($expected_user['user_firstname'], $return->user_firstname);
            $this->assertSame($expected_user['user_lastname'], $return->user_lastname);
            $this->assertSame($expected_user['user_email'], $return->user_email);
            $this->assertSame($expected_user['status'], $return->status);

            // User Details
            $expected_user_details = static::$userDetailsData[$expect[$index]];
            $this->assertSame($expected_user_details['user_id'], (string)$return->userdetail->user_id);
            $this->assertSame($expected_user_details['address_line1'], (string)$return->userdetail->address_line1);
        }
    }

    public function testOK_NoArgumentGiven_MaxRecordMoreThenRecords_GET_api_v1_user_search()
    {
        // Data
        // No argument given at all, show all users
        // It should read from config named 'orbit.pagination.user.max_record'
        // It should fallback to whatever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.user.max_record', $max_record);
        Config::set('orbit.pagination.user.per_page', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 6, exlcude pink panther
        $this->assertSame(6, (int)$response->data->total_records);
        $this->assertSame(6, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(6, count($response->data->records));

        // It is ordered by first name by default so:
        $expect = array('catwoman', 'chuck', 'droopy', 'john', 'smith', 'optimus');
        $this->assertUsersInOrder($response->data->records, $expect);
    }

    public function testInvalidSortBy_GET_api_v1_user_search()
    {
        // Data
        $_GET['sortby'] = 'dummy';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('validation.orbit.empty.user_sortby');
        $this->assertSame(Status::INVALID_ARGUMENT, (int)$response->code);
        $this->assertSame('error', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue(is_null($response->data->records));
    }

    public function testOK_OrderByRegisteredDateDESC_GET_api_v1_user_search()
    {
        // Data
        $_GET['sortby'] = 'registered_date';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.user.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 6;
        Config::set('orbit.pagination.user.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records and returned should be 6, exlcude pink panther
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(6, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(6, count($response->data->records));


        // It is explicitly ordered by registered date desc so
        $expect = array('catwoman', 'droopy', 'optimus', 'chuck', 'smith', 'john');
        $this->assertUsersInOrder($response->data->records, $expect);
    }

    public function testOK_OrderByRegisteredDateASC_GET_api_v1_user_search()
    {
        // Data
        $_GET['sortby'] = 'registered_date';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 6;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records and returned should be 6, exlcude pink panther
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(6, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(6, count($response->data->records));

        // It is explicitly ordered by registered date asc so
        $expect = array('john', 'smith', 'chuck', 'optimus', 'droopy', 'catwoman');
        $this->assertUsersInOrder($response->data->records, $expect);
    }

    public function testOK_SearchUsername_GET_api_v1_user_search()
    {
        // Data
        $_GET['username'] = array('chuck', 'john');
        $_GET['sortby'] = 'username'; // asc by default

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame(2, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array('chuck', 'john');
        $this->assertUsersInOrder($response->data->records, $expect);
    }

    public function testOK_SearchUsername_OrderByUsernameASC_GET_api_v1_user_search()
    {
        // Data: explicitly specify sort direction
        $_GET['username'] = array('chuck', 'john');
        $_GET['sortby'] = 'username';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array('chuck', 'john');
        $this->assertUsersInOrder($response->data->records, $expect);
    }

    public function testOK_SearchUsernameLike_OrderByUsernameASC_GET_api_v1_user_search()
    {
        // Data
        $_GET['username_like'] = 'smi';
        $_GET['sortby'] = 'username';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 1;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 1 and returned records 1
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(1, count($response->data->records));

        $expect = array('smith');

        $this->assertUsersInOrder($response->data->records, $expect);
    }

    public function testOK_SearchUsername_NotFound_GET_api_v1_user_search()
    {
        // Data
        $_GET['username'] = array('not-exists');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('statuses.orbit.nodata.user');

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue( is_null($response->data->records) );
    }

    public function testOK_SearchFirstName_OrderByFirstNameASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['firstname'] = array('Cat', 'Chuck');
        $_GET['sortby'] = 'firstname';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array('catwoman', 'chuck');
        $this->assertUsersInOrder($response->data->records, $expect);
    }

    public function testOK_SearchFirstName_OrderByFirstNameDESC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['firstname'] = array('Cat', 'Chuck');
        $_GET['sortby'] = 'firstname';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array('chuck', 'catwoman');
        $this->assertUsersInOrder($response->data->records, $expect);
    }

    public function testOK_SearchFirstName_Like_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['firstname_like'] = 'Droo';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 1;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 1 and returned records 1
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(1, count($response->data->records));

        $this->assertUsersInOrder($response->data->records, array('droopy'));
    }

    public function testOK_SearchFirstName_NotFound_GET_api_v1_user_search()
    {
        // Data
        $_GET['firstname'] = array('not-exists');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('statuses.orbit.nodata.user');

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue( is_null($response->data->records) );
    }

    public function testOK_SearchLastName_OrderByLastNameASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['lastname'] = array('Woman', 'Norris');
        $_GET['sortby'] = 'lastname';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $this->assertUsersInOrder($response->data->records, array('chuck', 'catwoman'));
    }

    public function testOK_SearchLastName_OrderByLastNameDESC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['lastname'] = array('Woman', 'Norris');
        $_GET['sortby'] = 'lastname';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $this->assertUsersInOrder($response->data->records, array('catwoman', 'chuck'));
    }

    public function testOK_SearchLastNameLike_OrderByLastNameDESC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['lastname_like'] = 'Do';
        $_GET['sortby'] = 'lastname';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $this->assertUsersInOrder($response->data->records, array('droopy', 'john'));
    }

    public function testOK_SearchLastName_NotFound_GET_api_v1_user_search()
    {
        // Data
        $_GET['lastname'] = array('not-exists');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('statuses.orbit.nodata.user');

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue( is_null($response->data->records) );
    }

    public function testOK_SearchEmail_OrderByEmailASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['email'] = array('catwoman@localhost.org', 'chuck@localhost.org');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchEmail_OrderByEmailDESC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['email'] = array('catwoman@localhost.org', 'chuck@localhost.org');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            )
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchEmailLike_OrderByEmailASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['email_like'] = '@localhost.org';
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 6;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 6 and returned records 6
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(6, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(6, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '6',
                'username'          => 'droopy',
                'firstname'         => 'Droopy',
                'lastname'          => 'Dog',
                'email'             => 'droopy@localhost.org',
                'status'            => 'pending'
            ),
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '4',
                'username'          => 'optimus',
                'firstname'         => 'Optimus',
                'lastname'          => 'Prime',
                'email'             => 'optimus@localhost.org',
                'status'            => 'blocked'
            ),
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchEmail_NotFound_GET_api_v1_user_search()
    {
        // Data
        $_GET['email'] = array('not-exists@localhost.org');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('statuses.orbit.nodata.user');

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue( is_null($response->data->records) );
    }

    public function testOK_SearchStatusActive_OrderByEmailASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('active');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 4 and returned records 4
        $this->assertSame(4, (int)$response->data->total_records);
        $this->assertSame(4, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(4, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            ),
        );

        // catwoman, chuck, john, smith

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(4, $matches);
    }

    public function testOK_SearchStatusBlocked_OrderByEmailASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('blocked');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 1 and returned records 1
        $this->assertSame(1, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(1, count($response->data->records));

        $expect = array(
            array(
                'id'                => '4',
                'username'          => 'optimus',
                'firstname'         => 'Optimus',
                'lastname'          => 'Prime',
                'email'             => 'optimus@localhost.org',
                'status'            => 'blocked'
            ),
        );

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(1, $matches);
    }

    public function testOK_SearchStatusPending_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('pending');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 1 and returned records 1
        $this->assertSame(1, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(1, count($response->data->records));

        $expect = array(
            array(
                'id'                => '6',
                'username'          => 'droopy',
                'firstname'         => 'Droopy',
                'lastname'          => 'Dog',
                'email'             => 'droopy@localhost.org',
                'status'            => 'pending'
            ),
        );

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(1, $matches);
    }

    public function testOK_SearchStatusDeleted_NoDataShouldReturned_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('deleted');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('statuses.orbit.nodata.user');

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue( is_null($response->data->records) );
    }

    public function testOK_SearchStatusActive_OrderByEmailASC_Take2_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('active');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';
        $_GET['take'] = 3;

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 4;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 4 and returned records 3
        $this->assertSame(4, (int)$response->data->total_records);
        $this->assertSame(3, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(3, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
        );

        // catwoman, chuck, john

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(3, $matches);
    }

    public function testOK_SearchStatusActive_OrderByEmailASC_Take2_Skip2_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('active');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';
        $_GET['take'] = 2;
        $_GET['skip'] = 2;

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 4;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 4 and returned records 2
        $this->assertSame(4, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            ),
        );

        // john, smith

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(2, $matches);
    }

    public function testOK_SearchUserId_OrderByEmailASC_Take2_Skip0_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['user_id'] = array(1, 2);
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';
        $_GET['take'] = 2;
        $_GET['skip'] = 0;

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 4;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame(2, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            ),
        );

        // catwoman, chuck, john, smith

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(2, $matches);
    }

    public function testOK_SearchRoleId_OrderByEmailASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['role_id'] = array('4');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 1 and returned records 1
        $this->assertSame(1, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(1, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
        );

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(1, $matches);
    }

    /**
     * Checks that the records returned from user search have the expected data and are in the expected order.
     *
     * Checks user ID, user name, first name, last name, email, and status only.
     *
     * @param array $records records returned.
     * @param array $expectedOrder array of expected usernames in order.
     */
    private function assertUsersInOrder($records, $expectedOrder)
    {
        foreach ($records as $index => $return) {
            $expected_user = static::$userData[$expectedOrder[$index]];
            $this->assertSame($expected_user['user_id'], (string)$return->user_id);
            $this->assertSame($expected_user['username'], $return->username);
            $this->assertSame($expected_user['user_firstname'], $return->user_firstname);
            $this->assertSame($expected_user['user_lastname'], $return->user_lastname);
            $this->assertSame($expected_user['user_email'], $return->user_email);
            $this->assertSame($expected_user['status'], $return->status);
        }
    }
}
