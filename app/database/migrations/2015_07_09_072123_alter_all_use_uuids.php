<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAllUseUuids extends Migration {

    /**
     * Run the migrations.
     *
     * @throws
     */
    public function up()
    {
        $builder = DB::connection()->getSchemaBuilder();
        $builder->blueprintResolver(function ($table, $callback) {
            return new OrbitBlueprint($table, $callback);
        });

        foreach($this->getColumnNames() as $args)
        {
            call_user_func_array([$this, 'alterTable'], $args);
        }
    }

    public function down()
    {
        # DO NOTHING
    }

    private function alterTable($tableName, $columnName, $isNullable)
    {
        $specialLength = ['session_id' => 40];
        $tableName     = DB::getTablePrefix() . $tableName;

        if (array_key_exists($columnName, $specialLength)) {
            $stmt = ("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` CHAR({$specialLength[$columnName]}) CHARACTER SET ASCII COLLATE ASCII_BIN;");
        } elseif ($isNullable == 'YES') {
            $stmt = ("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` CHAR(16) CHARACTER SET ASCII COLLATE ASCII_BIN;");
        } else {
            $stmt = ("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` CHAR(16) CHARACTER SET ASCII COLLATE ASCII_BIN NOT NULL;");
        }

        $ok = DB::statement($stmt);
        if (!$ok)
        {
            throw \Exception("FAIL: " . $stmt);
        }
    }

    private function getColumnNames() {
        return [[ 'activities', 'activity_id', 'NO'],
        [ 'activities', 'user_id', 'YES'],
        [ 'activities', 'role_id', 'YES'],
        [ 'activities', 'object_id', 'YES'],
        [ 'activities', 'product_id', 'YES'],
        [ 'activities', 'coupon_id', 'YES'],
        [ 'activities', 'promotion_id', 'YES'],
        [ 'activities', 'event_id', 'YES'],
        [ 'activities', 'location_id', 'YES'],
        [ 'activities', 'session_id', 'YES'],
        [ 'activities', 'staff_id', 'YES'],
        [ 'activities', 'parent_id', 'YES'],
        [ 'apikeys', 'apikey_id', 'NO'],
        [ 'apikeys', 'user_id', 'NO'],
        [ 'cart_coupons', 'cart_coupon_id', 'NO'],
        [ 'cart_coupons', 'issued_coupon_id', 'NO'],
        [ 'cart_coupons', 'object_id', 'NO'],
        [ 'cart_details', 'cart_detail_id', 'NO'],
        [ 'cart_details', 'cart_id', 'YES'],
        [ 'cart_details', 'product_id', 'YES'],
        [ 'cart_details', 'product_variant_id', 'YES'],
        [ 'carts', 'cart_id', 'NO'],
        [ 'carts', 'customer_id', 'YES'],
        [ 'carts', 'merchant_id', 'YES'],
        [ 'carts', 'retailer_id', 'YES'],
        [ 'carts', 'cashier_id', 'YES'],
        [ 'categories', 'category_id', 'NO'],
        [ 'categories', 'merchant_id', 'NO'],
        [ 'categories', 'created_by', 'YES'],
        [ 'categories', 'modified_by', 'YES'],
        [ 'countries', 'country_id', 'NO'],
        [ 'custom_permission', 'custom_permission_id', 'NO'],
        [ 'custom_permission', 'user_id', 'NO'],
        [ 'custom_permission', 'permission_id', 'NO'],
        [ 'employee_retailer', 'employee_retailer_id', 'NO'],
        [ 'employee_retailer', 'employee_id', 'NO'],
        [ 'employee_retailer', 'retailer_id', 'NO'],
        [ 'employees', 'employee_id', 'NO'],
        [ 'employees', 'user_id', 'NO'],
        [ 'employees', 'employee_id_char', 'YES'],
        [ 'event_retailer', 'event_retailer_id', 'NO'],
        [ 'event_retailer', 'event_id', 'NO'],
        [ 'event_retailer', 'retailer_id', 'NO'],
        [ 'events', 'event_id', 'NO'],
        [ 'events', 'merchant_id', 'NO'],
        [ 'events', 'link_object_id1', 'YES'],
        [ 'events', 'link_object_id2', 'YES'],
        [ 'events', 'link_object_id3', 'YES'],
        [ 'events', 'link_object_id4', 'YES'],
        [ 'events', 'link_object_id5', 'YES'],
        [ 'events', 'created_by', 'YES'],
        [ 'events', 'modified_by', 'YES'],
        [ 'issued_coupons', 'issued_coupon_id', 'NO'],
        [ 'issued_coupons', 'promotion_id', 'NO'],
        [ 'issued_coupons', 'transaction_id', 'YES'],
        [ 'issued_coupons', 'user_id', 'YES'],
        [ 'issued_coupons', 'issuer_retailer_id', 'YES'],
        [ 'media', 'media_id', 'NO'],
        [ 'media', 'object_id', 'YES'],
        [ 'media', 'modified_by', 'YES'],
        [ 'merchant_taxes', 'merchant_tax_id', 'NO'],
        [ 'merchant_taxes', 'merchant_id', 'NO'],
        [ 'merchant_taxes', 'created_by', 'YES'],
        [ 'merchant_taxes', 'modified_by', 'YES'],
        [ 'merchants', 'merchant_id', 'NO'],
        [ 'merchants', 'user_id', 'NO'],
        [ 'merchants', 'city_id', 'YES'],
        [ 'merchants', 'country_id', 'YES'],
        [ 'merchants', 'parent_id', 'YES'],
        [ 'merchants', 'modified_by', 'NO'],
        [ 'permission_role', 'permission_role_id', 'NO'],
        [ 'permission_role', 'role_id', 'NO'],
        [ 'permission_role', 'permission_id', 'NO'],
        [ 'permissions', 'permission_id', 'NO'],
        [ 'permissions', 'modified_by', 'NO'],
        [ 'personal_interests', 'personal_interest_id', 'NO'],
        [ 'personal_interests', 'created_by', 'YES'],
        [ 'personal_interests', 'modified_by', 'YES'],
        [ 'pos_quick_products', 'pos_quick_product_id', 'NO'],
        [ 'pos_quick_products', 'product_id', 'NO'],
        [ 'pos_quick_products', 'merchant_id', 'NO'],
        [ 'pos_quick_products', 'retailer_id', 'YES'],
        [ 'product_attribute_values', 'product_attribute_value_id', 'NO'],
        [ 'product_attribute_values', 'product_attribute_id', 'NO'],
        [ 'product_attribute_values', 'created_by', 'YES'],
        [ 'product_attribute_values', 'modified_by', 'YES'],
        [ 'product_attributes', 'product_attribute_id', 'NO'],
        [ 'product_attributes', 'merchant_id', 'YES'],
        [ 'product_attributes', 'created_by', 'YES'],
        [ 'product_attributes', 'modified_by', 'YES'],
        [ 'product_retailer', 'product_retailer_id', 'NO'],
        [ 'product_retailer', 'product_id', 'NO'],
        [ 'product_retailer', 'retailer_id', 'NO'],
        [ 'product_variants', 'product_variant_id', 'NO'],
        [ 'product_variants', 'product_id', 'YES'],
        [ 'product_variants', 'product_attribute_value_id1', 'YES'],
        [ 'product_variants', 'product_attribute_value_id2', 'YES'],
        [ 'product_variants', 'product_attribute_value_id3', 'YES'],
        [ 'product_variants', 'product_attribute_value_id4', 'YES'],
        [ 'product_variants', 'product_attribute_value_id5', 'YES'],
        [ 'product_variants', 'merchant_id', 'YES'],
        [ 'product_variants', 'retailer_id', 'YES'],
        [ 'product_variants', 'created_by', 'YES'],
        [ 'product_variants', 'modified_by', 'YES'],
        [ 'products', 'product_id', 'NO'],
        [ 'products', 'merchant_tax_id1', 'YES'],
        [ 'products', 'merchant_tax_id2', 'YES'],
        [ 'products', 'merchant_id', 'NO'],
        [ 'products', 'attribute_id1', 'YES'],
        [ 'products', 'attribute_id2', 'YES'],
        [ 'products', 'attribute_id3', 'YES'],
        [ 'products', 'attribute_id4', 'YES'],
        [ 'products', 'attribute_id5', 'YES'],
        [ 'products', 'category_id1', 'YES'],
        [ 'products', 'category_id2', 'YES'],
        [ 'products', 'category_id3', 'YES'],
        [ 'products', 'category_id4', 'YES'],
        [ 'products', 'category_id5', 'YES'],
        [ 'products', 'created_by', 'YES'],
        [ 'products', 'modified_by', 'YES'],
        [ 'promotion_retailer', 'promotion_retailer_id', 'NO'],
        [ 'promotion_retailer', 'promotion_id', 'NO'],
        [ 'promotion_retailer', 'retailer_id', 'NO'],
        [ 'promotion_retailer_redeem', 'promotion_retailer_redeem_id', 'NO'],
        [ 'promotion_retailer_redeem', 'promotion_id', 'NO'],
        [ 'promotion_retailer_redeem', 'retailer_id', 'NO'],
        [ 'promotion_rules', 'promotion_rule_id', 'NO'],
        [ 'promotion_rules', 'promotion_id', 'NO'],
        [ 'promotion_rules', 'rule_object_id1', 'YES'],
        [ 'promotion_rules', 'rule_object_id2', 'YES'],
        [ 'promotion_rules', 'rule_object_id3', 'YES'],
        [ 'promotion_rules', 'rule_object_id4', 'YES'],
        [ 'promotion_rules', 'rule_object_id5', 'YES'],
        [ 'promotion_rules', 'discount_object_id1', 'YES'],
        [ 'promotion_rules', 'discount_object_id2', 'YES'],
        [ 'promotion_rules', 'discount_object_id3', 'YES'],
        [ 'promotion_rules', 'discount_object_id4', 'YES'],
        [ 'promotion_rules', 'discount_object_id5', 'YES'],
        [ 'promotions', 'promotion_id', 'NO'],
        [ 'promotions', 'merchant_id', 'NO'],
        [ 'promotions', 'created_by', 'YES'],
        [ 'promotions', 'modified_by', 'YES'],
        [ 'roles', 'role_id', 'NO'],
        [ 'roles', 'modified_by', 'NO'],
        [ 'sessions', 'session_id', 'YES'],
        [ 'settings', 'setting_id', 'NO'],
        [ 'settings', 'object_id', 'YES'],
        [ 'settings', 'modified_by', 'YES'],
        [ 'tokens', 'token_id', 'NO'],
        [ 'tokens', 'object_id', 'YES'],
        [ 'tokens', 'user_id', 'YES'],
        [ 'transaction_detail_coupons', 'transaction_detail_coupon_id', 'NO'],
        [ 'transaction_detail_coupons', 'transaction_detail_id', 'YES'],
        [ 'transaction_detail_coupons', 'transaction_id', 'YES'],
        [ 'transaction_detail_coupons', 'promotion_id', 'YES'],
        [ 'transaction_detail_coupons', 'category_id1', 'YES'],
        [ 'transaction_detail_coupons', 'category_id2', 'YES'],
        [ 'transaction_detail_coupons', 'category_id3', 'YES'],
        [ 'transaction_detail_coupons', 'category_id4', 'YES'],
        [ 'transaction_detail_coupons', 'category_id5', 'YES'],
        [ 'transaction_detail_promotions', 'transaction_detail_promotion_id', 'NO'],
        [ 'transaction_detail_promotions', 'transaction_detail_id', 'YES'],
        [ 'transaction_detail_promotions', 'transaction_id', 'YES'],
        [ 'transaction_detail_promotions', 'promotion_id', 'YES'],
        [ 'transaction_detail_taxes', 'transaction_detail_tax_id', 'NO'],
        [ 'transaction_detail_taxes', 'transaction_detail_id', 'YES'],
        [ 'transaction_detail_taxes', 'transaction_id', 'YES'],
        [ 'transaction_detail_taxes', 'tax_id', 'YES'],
        [ 'transaction_details', 'transaction_detail_id', 'NO'],
        [ 'transaction_details', 'transaction_id', 'YES'],
        [ 'transaction_details', 'product_id', 'YES'],
        [ 'transaction_details', 'product_variant_id', 'YES'],
        [ 'transaction_details', 'product_attribute_value_id1', 'YES'],
        [ 'transaction_details', 'product_attribute_value_id2', 'YES'],
        [ 'transaction_details', 'product_attribute_value_id3', 'YES'],
        [ 'transaction_details', 'product_attribute_value_id4', 'YES'],
        [ 'transaction_details', 'product_attribute_value_id5', 'YES'],
        [ 'transaction_details', 'merchant_tax_id1', 'YES'],
        [ 'transaction_details', 'merchant_tax_id2', 'YES'],
        [ 'transaction_details', 'attribute_id1', 'YES'],
        [ 'transaction_details', 'attribute_id2', 'YES'],
        [ 'transaction_details', 'attribute_id3', 'YES'],
        [ 'transaction_details', 'attribute_id4', 'YES'],
        [ 'transaction_details', 'attribute_id5', 'YES'],
        [ 'transactions', 'transaction_id', 'NO'],
        [ 'transactions', 'cashier_id', 'YES'],
        [ 'transactions', 'customer_id', 'YES'],
        [ 'transactions', 'merchant_id', 'YES'],
        [ 'transactions', 'retailer_id', 'YES'],
        [ 'user_details', 'user_detail_id', 'NO'],
        [ 'user_details', 'user_id', 'NO'],
        [ 'user_details', 'merchant_id', 'YES'],
        [ 'user_details', 'retailer_id', 'YES'],
        [ 'user_details', 'city_id', 'YES'],
        [ 'user_details', 'province_id', 'YES'],
        [ 'user_details', 'country_id', 'YES'],
        [ 'user_details', 'last_visit_shop_id', 'YES'],
        [ 'user_details', 'last_purchase_shop_id', 'YES'],
        [ 'user_details', 'last_spent_shop_id', 'YES'],
        [ 'user_details', 'modified_by', 'YES'],
        [ 'user_personal_interest', 'user_personal_interest_id', 'NO'],
        [ 'user_personal_interest', 'user_id', 'NO'],
        [ 'user_personal_interest', 'personal_interest_id', 'NO'],
        [ 'users', 'user_id', 'NO'],
        [ 'users', 'user_role_id', 'NO'],
        [ 'users', 'modified_by', 'NO'],
        [ 'widget_retailer', 'widget_retailer_id', 'NO'],
        [ 'widget_retailer', 'widget_id', 'NO'],
        [ 'widget_retailer', 'retailer_id', 'NO'],
        [ 'widgets', 'widget_id', 'NO'],
        [ 'widgets', 'widget_object_id', 'YES'],
        [ 'widgets', 'merchant_id', 'YES'],
        [ 'widgets', 'created_by', 'YES'],
        [ 'widgets', 'modified_by', 'YES']];
    }


}
