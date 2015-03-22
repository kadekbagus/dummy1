<?php
/**
 * Unit testing for Product model.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class ProductTest extends OrbitTestCase
{
    protected static $merchants = [];
    protected static $retailers = [];
    protected static $products = [];
    protected static $variants = [];
    protected static $attributes = [];
    protected static $attributeValues = [];
    protected static $productVariants = [];
    protected static $transactions = [];
    protected static $transactionDetails = [];

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

        // Insert dummy data on apikeys
        DB::statement("INSERT INTO `{$apikey_table}`
                (`apikey_id`, `api_key`, `api_secret_key`, `user_id`, `status`, `created_at`, `updated_at`)
                VALUES
                (1, 'abc123', 'abc12345678910', '1', 'active', '2014-10-19 20:02:01', '2014-10-19 20:03:01'),
                (2, 'bcd234', 'bcd23456789010', '2', 'active', '2014-10-19 20:02:02', '2014-10-19 20:03:02'),
                (3, 'cde345', 'cde34567890100', '3', 'active', '2014-10-19 20:02:03', '2014-10-19 20:03:03'),
                (4, 'def123', 'def12345678901', '1', 'active', '2014-10-19 20:02:04', '2014-10-19 20:03:04'),
                (5, 'efg212', 'efg09876543212', '4', 'active', '2014-10-19 20:02:05', '2014-10-19 20:03:05')"
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
                ('5', 'add_product', 'Add Product', 'product', 'Product', '0', '2', '1', NOW(), NOW())"
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

        // Insert Dummy Merchants
        static::$merchants = [
            [
                'merchant_id'   => 1,
                'name'          => 'Matahari',
                'status'        => 'active',
                'object_type'   => 'merchant',
                'user_id'       => 3,
            ],
            [
                'merchant_id'   => 2,
                'name'          => 'Ace Hardware',
                'status'        => 'active',
                'object_type'   => 'merchant',
                'user_id'       => 4
            ],
        ];
        foreach (static::$merchants as $merchant) {
            DB::table('merchants')->insert($merchant);
        }

        // Insert Dummy Retailers
        static::$retailers = [
            [
                'merchant_id'   => 3,
                'name'          => 'Matahari Mall Denpasar',
                'status'        => 'active',
                'object_type'   => 'retailer',
                'user_id'       => 3,
                'parent_id'     => 1,
            ],
            [
                'merchant_id'   => 4,
                'name'          => 'Ace Hardware Sunset Road',
                'status'        => 'active',
                'object_type'   => 'retailer',
                'user_id'       => 4,
                'parent_id'     => 2,
            ],
            [
                'merchant_id'   => 5,
                'name'          => 'Matahari Sunset Road',
                'status'        => 'active',
                'object_type'   => 'retailer',
                'user_id'       => 4,
                'parent_id'     => 1,
            ],
        ];
        foreach (static::$retailers as $retailer) {
            DB::table('merchants')->insert($retailer);
        }

        // Insert dummy Product Attributes
        static::$attributes = [
            [
                'product_attribute_id'      => 1,
                'product_attribute_name'    => 'Size',
                'merchant_id'               => 1,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 2,
                'product_attribute_name'    => 'Color',
                'merchant_id'               => 1,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 3,
                'product_attribute_name'    => 'Material',
                'merchant_id'               => 1,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 4,
                'product_attribute_name'    => 'Class',
                'merchant_id'               => 1,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 5,
                'product_attribute_name'    => 'Origin',
                'merchant_id'               => 1,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 6,
                'product_attribute_name'    => 'Size',
                'merchant_id'               => 2,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 7,
                'product_attribute_name'    => 'Material',
                'merchant_id'               => 2,
                'status'                    => 'active'
            ],
        ];
        foreach (static::$attributes as $attr) {
            DB::table('product_attributes')->insert($attr);
        }

        // Insert Dummy Product Attribute Value
        $attributeValues = [
            [
                'product_attribute_value_id'    => 1,
                'product_attribute_id'          => 1,
                'value'                         => '27',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 2,
                'product_attribute_id'          => 1,
                'value'                         => '28',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 3,
                'product_attribute_id'          => 1,
                'value'                         => '29',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 4,
                'product_attribute_id'          => 1,
                'value'                         => '30',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 5,
                'product_attribute_id'          => 2,
                'value'                         => 'White',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 6,
                'product_attribute_id'          => 2,
                'value'                         => 'Gray',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 7,
                'product_attribute_id'          => 2,
                'value'                         => 'Black',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 8,
                'product_attribute_id'          => 3,
                'value'                         => 'Cotton',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 9,
                'product_attribute_id'          => 3,
                'value'                         => 'Spandex',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 10,
                'product_attribute_id'          => 6,
                'value'                         => 'Small',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 11,
                'product_attribute_id'          => 6,
                'value'                         => 'Medium',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 12,
                'product_attribute_id'          => 6,
                'value'                         => 'Big',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 13,
                'product_attribute_id'          => 7,
                'value'                         => 'Iron',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 14,
                'product_attribute_id'          => 7,
                'value'                         => 'Steel',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 15,
                'product_attribute_id'          => 4,
                'value'                         => 'KW 1',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 16,
                'product_attribute_id'          => 4,
                'value'                         => 'KW 2',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 17,
                'product_attribute_id'          => 5,
                'value'                         => 'USA',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 18,
                'product_attribute_id'          => 5,
                'value'                         => 'Bandung',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 19,
                'product_attribute_id'          => 1,
                'value'                         => '14',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 20,
                'product_attribute_id'          => 1,
                'value'                         => '15',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 21,
                'product_attribute_id'          => 1,
                'value'                         => '16',
                'status'                        => 'active'
            ],
        ];
        foreach ($attributeValues as $value) {
            DB::table('product_attribute_values')->insert($value);
        }

        // Insert dummy products
        static::$products = [
            [
                'product_id'    => 1,
                'product_name'  => 'Kemeja Mahal',
                'product_code'  => 'SKU-001',
                'upc_code'      => 'UPC-001',
                'price'         => 500000,
                'status'        => 'active',
                'merchant_id'   => 1,
            ],
            [
                'product_id'    => 2,
                'product_name'  => 'Celana Murah',
                'product_code'  => 'SKU-002',
                'upc_code'      => 'UPC-002',
                'price'         => 30000,
                'status'        => 'active',
                'merchant_id'   => 1
            ],
            [
                'product_id'    => 3,
                'product_name'  => 'Kunci Obeng',
                'product_code'  => 'SKU-001',
                'upc_code'      => 'UPC-001',
                'price'         => 125000,
                'status'        => 'active',
                'merchant_id'   => 2
            ],
        ];
        foreach (static::$products as $product) {
            DB::table('products')->insert($product);
        }

        // Insert dummy product variants
        static::$variants = [
            [
                'product_variant_id'            => 1,
                'product_id'                    => 1,
                'price'                         => 123000,
                'upc'                           => 'UPC-101',
                'sku'                           => 'SKU-101',
                'product_attribute_value_id1'   => 19,
                'merchant_id'                   => 1,
                'status'                        => 'active',
                'default_variant'               => 'no',
            ],
            [
                'product_variant_id'            => 2,
                'product_id'                    => 1,
                'price'                         => 500000,
                'upc'                           => 'UPC-001',
                'sku'                           => 'SKU-001',
                'merchant_id'                   => 1,
                'status'                        => 'active',
                'default_variant'               => 'yes'
            ],
            [
                'product_variant_id'            => 3,
                'product_id'                    => 2,
                'price'                         => 5500,
                'upc'                           => 'UPC-102',
                'sku'                           => 'SKU-102',
                'product_attribute_value_id1'   => 5,
                'merchant_id'                   => 1,
                'status'                        => 'active',
                'default_variant'               => 'no'
            ],
            [
                'product_variant_id'            => 4,
                'product_id'                    => 2,
                'price'                         => 5500,
                'upc'                           => 'UPC-002',
                'sku'                           => 'SKU-002',
                'merchant_id'                   => 1,
                'status'                        => 'active',
                'default_variant'               => 'yes'
            ]
        ];
        foreach (static::$variants as $variant) {
            DB::table('product_variants')->insert($variant);
        }

        // Insert dummy transactions
        static::$transactions = [
            [
                'transaction_id'    => 1,
                'transaction_code'  => 'T001',
                'cashier_id'        => 99,
                'customer_id'       => 3,
                'retailer_id'       => 3,
                'merchant_id'       => 1,
                'status'            => 'paid',
            ],
        ];
        foreach (static::$transactions as $transaction) {
            DB::table('transactions')->insert($transaction);
        }

        // Insert dummy transaction details
        static::$transactionDetails = [
            [
                'transaction_detail_id'     => 1,
                'transaction_id'            => 1,
                'product_id'                => 1,
                'product_name'              => 'Kemeja Mahal',
                'price'                     => 3,
                'product_code'              => 'SKU-001',
                'sku'                       => 'SKU-001',
                'upc'                       => 'UPC-001',
                'product_variant_id'        => 1,
                'variant_price'             => 8000,
                'variant_sku'               => 'SKU-001',
                'variant_upc'               => 'UPC-001'
            ],
        ];
        foreach (static::$transactionDetails as $tdetails) {
            DB::table('transaction_details')->insert($tdetails);
        }
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
        $attributes_table = static::$dbPrefix . 'product_attributes';
        $attribute_values_table = static::$dbPrefix . 'product_attribute_values';
        $variants_table = static::$dbPrefix . 'product_variants';
        $products_table = static::$dbPrefix . 'products';
        $transactions_table = static::$dbPrefix . 'transactions';
        $transaction_details_table = static::$dbPrefix . 'transaction_details';
        DB::unprepared("TRUNCATE `{$apikey_table}`;
                        TRUNCATE `{$user_table}`;
                        TRUNCATE `{$user_detail_table}`;
                        TRUNCATE `{$role_table}`;
                        TRUNCATE `{$custom_permission_table}`;
                        TRUNCATE `{$permission_role_table}`;
                        TRUNCATE `{$permission_table}`;
                        TRUNCATE `{$merchant_table}`;
                        TRUNCATE `{$attributes_table}`;
                        TRUNCATE `{$attribute_values_table}`;
                        TRUNCATE `{$variants_table}`;
                        TRUNCATE `{$products_table}`;
                        TRUNCATE `{$transactions_table}`;
                        TRUNCATE `{$transaction_details_table}`;
                        ");
    }

    public function tearDown()
    {
        Config::set('model:product.variant.exclude_default', NULL);
        Config::set('model:product.variant.include_transaction_status', NULL);
    }

    public function testObjectInstance()
    {
        $product = new Product();
        $this->assertInstanceOf('Product', $product);
    }

    public function testProductID1_WithVariants_includingTheDefault()
    {
        $product1 = Product::with(['variants'])->find(1);
        $this->assertSame(2, count($product1->variants));
    }

    public function testProductID1_WithVariants_excludingTheDefault()
    {
        Config::set('model:product.variant.exclude_default', 'yes');
        $product1 = Product::with(['variants'])
                           ->find(1);
        $this->assertSame(1, count($product1->variants));
    }

    public function testProductID1_WithVariants_includeVariantTransactionStatus()
    {
        Config::set('model:product.variant.include_transaction_status', 'yes');
        Config::set('model:product.variant.exclude_default', 'yes');
        $product1 = Product::with(['variants'])
                           ->find(1);
        $this->assertSame(1, count($product1->variants));
        $this->assertSame('yes', $product1->variants[0]->has_transaction);
    }

    public function testProductID2_WithVariants_includingTheDefault()
    {
        $product2 = Product::with(['variants'])->find(2);
        $this->assertSame(2, count($product2->variants));
    }

    public function testProductID2_WithVariants_excludingTheDefault()
    {
        Config::set('model:product.variant.exclude_default', 'yes');
        $product2 = Product::with(['variants'])->find(2);
        $this->assertSame(1, count($product2->variants));
    }

    public function testProductID2_WithVariants_includeVariantTransactionStatus()
    {
        Config::set('model:product.variant.include_transaction_status', 'yes');
        Config::set('model:product.variant.exclude_default', 'yes');
        $product2 = Product::with(['variants'])
                           ->find(2);
        $this->assertSame(1, count($product2->variants));
        $this->assertSame('no', $product2->variants[0]->has_transaction);
    }
}
