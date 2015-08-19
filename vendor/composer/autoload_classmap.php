<?php

// autoload_classmap.php @generated by Composer

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
    'Activity' => $baseDir . '/app/models/Activity.php',
    'ActivityAPIController' => $baseDir . '/app/controllers/api/v1/ActivityAPIController.php',
    'AddIncrementsToApikeyId' => $baseDir . '/app/database/migrations/2014_10_16_055603_add_increments_to_apikey_id.php',
    'AddIncrementsToCustomPermissionId' => $baseDir . '/app/database/migrations/2014_10_16_070246_add_increments_to_custom_permission_id.php',
    'AddIncrementsToPermissionId' => $baseDir . '/app/database/migrations/2014_10_16_070755_add_increments_to_permission_id.php',
    'AddIncrementsToPermissionRoleId' => $baseDir . '/app/database/migrations/2014_10_16_071152_add_increments_to_permission_role_id.php',
    'AddIncrementsToRoleId' => $baseDir . '/app/database/migrations/2014_10_16_071435_add_increments_to_role_id.php',
    'AddIncrementsToUserId' => $baseDir . '/app/database/migrations/2014_10_16_071723_add_increments_to_user_id.php',
    'AddIndexesToApikeys' => $baseDir . '/app/database/migrations/2014_10_16_021255_add_indexes_to_apikeys.php',
    'AddIndexesToCustomPermission' => $baseDir . '/app/database/migrations/2014_10_16_021435_add_indexes_to_custom_permission.php',
    'AddIndexesToMediaAndChangeDbEngine' => $baseDir . '/app/database/migrations/2014_12_08_032713_add_indexes_to_media_and_change_db_engine.php',
    'AddIndexesToMerchants' => $baseDir . '/app/database/migrations/2014_11_07_025357_add_indexes_to_merchants.php',
    'AddIndexesToPermissions' => $baseDir . '/app/database/migrations/2014_10_16_021700_add_indexes_to_permissions.php',
    'AddIndexesToPermissonRole' => $baseDir . '/app/database/migrations/2014_10_16_021815_add_indexes_to_permisson_role.php',
    'AddIndexesToUsers' => $baseDir . '/app/database/migrations/2014_10_16_020929_add_indexes_to_users.php',
    'AddUniqueConstraintToApikeys' => $baseDir . '/app/database/migrations/2014_10_21_074413_add_unique_constraint_to_apikeys.php',
    'Alter7TablesEngineInnodb' => $baseDir . '/app/database/migrations/2014_10_28_073137_alter_7_tables_engine_innodb.php',
    'AlterAddColumnOnTransactiondetail' => $baseDir . '/app/database/migrations/2015_01_05_032336_alter_add_column_on_transactiondetail.php',
    'AlterAddTransactionidOnIssuedcoupon' => $baseDir . '/app/database/migrations/2015_03_04_093851_alter_add_transactionid_on_issuedcoupon.php',
    'AlterCartDetailsTableAddStatus' => $baseDir . '/app/database/migrations/2015_01_05_031916_alter_cart_details_table_add_status.php',
    'AlterPermissionsAddPermissionDefaultValueColumn' => $baseDir . '/app/database/migrations/2014_10_23_112725_alter_permissions_add_permission_default_value_column.php',
    'AlterPosQuickProductsAddStatus' => $baseDir . '/app/database/migrations/2015_01_25_062448_alter_pos_quick_products_add_status.php',
    'AlterProductCategoryAddCategoryFamilyLevelColumn' => $baseDir . '/app/database/migrations/2014_11_14_023311_alter_product_category_add_category_family_level_column.php',
    'AlterRolesAddRoleOrderColumn' => $baseDir . '/app/database/migrations/2014_10_23_111801_alter_roles_add_role_order_column.php',
    'AlterSettingsAddStatusField' => $baseDir . '/app/database/migrations/2015_02_23_033835_alter_settings_add_status_field.php',
    'AlterTableActivitiesAddActivityNameLong' => $baseDir . '/app/database/migrations/2015_01_26_065737_alter_table_activities_add_activity_name_long.php',
    'AlterTableActivitiesAddGenderAndCashierName' => $baseDir . '/app/database/migrations/2015_02_27_100024_alter_table_activities_add_gender_and_cashier_name.php',
    'AlterTableActivitiesAddHttpMethodPostDataUrl' => $baseDir . '/app/database/migrations/2015_02_10_023215_alter_table_activities_add_http_method_post_data_url.php',
    'AlterTableActivitiesAddIndexColumn' => $baseDir . '/app/database/migrations/2015_05_19_072205_alter_table_activities_add_index_column.php',
    'AlterTableActivitiesAddModuleName' => $baseDir . '/app/database/migrations/2015_02_27_093101_alter_table_activities_add_module_name.php',
    'AlterTableActivitiesAddParentId' => $baseDir . '/app/database/migrations/2015_02_13_112353_alter_table_activities_add_parent_id.php',
    'AlterTableActivitiesAddPromotionCouponEventProductColumn' => $baseDir . '/app/database/migrations/2015_02_27_074521_alter_table_activities_add_promotion_coupon_event_product_column.php',
    'AlterTableActivitiesAddSessionIdColumn' => $baseDir . '/app/database/migrations/2015_06_25_040310_alter_table_activities_add_session_id_column.php',
    'AlterTableAddcolumnOnTransactionTable' => $baseDir . '/app/database/migrations/2015_01_05_062645_alter_table_addcolumn_on_transaction_table.php',
    'AlterTableCartDetailsDropColumnPriceEtc' => $baseDir . '/app/database/migrations/2015_01_08_085539_alter_table_cart_details_drop_column_price_etc.php',
    'AlterTableCartsAddMovedToPosFlagColumn' => $baseDir . '/app/database/migrations/2015_01_28_062801_alter_table_carts_add_moved_to_pos_flag_column.php',
    'AlterTableCartsDropColumnTotalItemEtc' => $baseDir . '/app/database/migrations/2015_01_08_083728_alter_table_carts_drop_column_total_item_etc.php',
    'AlterTableCartsRenameMovedToPosToCashierId' => $baseDir . '/app/database/migrations/2015_04_06_110211_alter_table_carts_rename_moved_to_pos_to_cashier_id.php',
    'AlterTableCategories' => $baseDir . '/app/database/migrations/2014_12_11_062433_alter_table_categories.php',
    'AlterTableCategoriesDropCategoryNameUnique' => $baseDir . '/app/database/migrations/2014_12_17_031758_alter_table_categories_drop_category_name_unique.php',
    'AlterTableChangeEngineInnodb' => $baseDir . '/app/database/migrations/2014_12_05_064558_alter_table_change_engine_innodb.php',
    'AlterTableDetailTaxesAddAutoincrement' => $baseDir . '/app/database/migrations/2015_03_18_082550_alter_table_detail_taxes_add_autoincrement.php',
    'AlterTableDetailtaxesAddnewcolumn' => $baseDir . '/app/database/migrations/2015_03_18_070902_alter_table_detailtaxes_addnewcolumn.php',
    'AlterTableDetailtaxesAddnewcolumnAgain' => $baseDir . '/app/database/migrations/2015_03_18_080416_alter_table_detailtaxes_addnewcolumn_again.php',
    'AlterTableEngineInnodb' => $baseDir . '/app/database/migrations/2014_11_14_025407_alter_table_engine_innodb.php',
    'AlterTableEventsAddColumnWidgetObjectType' => $baseDir . '/app/database/migrations/2015_02_11_101440_alter_table_events_add_column_widget_object_type.php',
    'AlterTableMerchantAddColumnModifiedBy' => $baseDir . '/app/database/migrations/2014_11_10_030122_alter_table_merchant_add_column_modified_by.php',
    'AlterTableMerchantTaxesAddColumnStatus' => $baseDir . '/app/database/migrations/2014_12_09_024548_alter_table_merchant_taxes_add_column_status.php',
    'AlterTableMerchantTaxesAddColumnTaxType' => $baseDir . '/app/database/migrations/2015_02_10_073405_alter_table_merchant_taxes_add_column_tax_type.php',
    'AlterTableMerchantTaxesAddTaxOrder' => $baseDir . '/app/database/migrations/2015_01_27_132819_alter_table_merchant_taxes_add_tax_order.php',
    'AlterTableMerchantsAddColumnHeaderFooter' => $baseDir . '/app/database/migrations/2015_02_05_063512_alter_table_merchants_add_column_header_footer.php',
    'AlterTableMerchantsAddColumnMobileDefaultLanguage' => $baseDir . '/app/database/migrations/2015_01_27_141333_alter_table_merchants_add_column_mobile_default_language.php',
    'AlterTableMerchantsAddColumnOmidOrid' => $baseDir . '/app/database/migrations/2014_11_24_015858_alter_table_merchants_add_column_omid_orid.php',
    'AlterTableMerchantsAddColumnPosLanguage' => $baseDir . '/app/database/migrations/2015_02_23_022721_alter_table_merchants_add_column_pos_language.php',
    'AlterTableMerchantsAddColumnProvince' => $baseDir . '/app/database/migrations/2015_05_06_083134_alter_table_merchants_add_column_province.php',
    'AlterTableMerchantsAddEnableShoppingCartColumn' => $baseDir . '/app/database/migrations/2015_06_03_094458_alter_table_merchants_add_enable_shopping_cart_column.php',
    'AlterTableMerchantsAddNewColumns' => $baseDir . '/app/database/migrations/2014_11_19_024135_alter_table_merchants_add_new_columns.php',
    'AlterTableMerchantsAddcolumnsAndRenamecolumn' => $baseDir . '/app/database/migrations/2014_11_20_033937_alter_table_merchants_addcolumns_and_renamecolumn.php',
    'AlterTableMerchantsR201411171326' => $baseDir . '/app/database/migrations/2014_11_17_080145_alter_table_merchants_r20141117_1326.php',
    'AlterTablePersonalInterestsAddStatusAndCreatedAndModifiedBy' => $baseDir . '/app/database/migrations/2015_01_16_144225_alter_table_personal_interests_add_status_and_created_and_modified_by.php',
    'AlterTablePosQuickProductsAddRetailerId' => $baseDir . '/app/database/migrations/2015_03_23_110430_alter_table_pos_quick_products_add_retailer_id.php',
    'AlterTableProductAttributeAndValuesAddModifiedBy' => $baseDir . '/app/database/migrations/2014_12_29_055702_alter_table_product_attribute_and_values_add_modified_by.php',
    'AlterTableProductAttributeValuesAddOrder' => $baseDir . '/app/database/migrations/2015_01_08_063719_alter_table_product_attribute_values_add_order.php',
    'AlterTableProductAttributeValuesChangePk' => $baseDir . '/app/database/migrations/2015_01_02_150733_alter_table_product_attribute_values_change_pk.php',
    'AlterTableProductAttributeValuesIdUnsigned' => $baseDir . '/app/database/migrations/2014_12_29_043911_alter_table_product_attribute_values_id_unsigned.php',
    'AlterTableProductAttributesAddMerchantId' => $baseDir . '/app/database/migrations/2014_12_29_230700_alter_table_product_attributes_add_merchant_id.php',
    'AlterTableProductFixProductCode' => $baseDir . '/app/database/migrations/2014_12_03_081118_alter_table_product_fix_product_code.php',
    'AlterTableProductVariantAddDefaultVariant' => $baseDir . '/app/database/migrations/2015_02_12_064548_alter_table_product_variant_add_default_variant.php',
    'AlterTableProductVariantsAddStock' => $baseDir . '/app/database/migrations/2014_12_29_231554_alter_table_product_variants_add_stock.php',
    'AlterTableProductsAddAttributes' => $baseDir . '/app/database/migrations/2014_12_29_042539_alter_table_products_add_attributes.php',
    'AlterTableProductsAddColumnCategoryId1To5' => $baseDir . '/app/database/migrations/2014_12_29_041959_alter_table_products_add_column_category_id1_to_5.php',
    'AlterTableProductsAsTheNewERDDec2' => $baseDir . '/app/database/migrations/2014_12_02_082538_alter_table_products_as_the_new_ERD_Dec_2.php',
    'AlterTableProductsDropUniqueIndexProductCodeUpcCode' => $baseDir . '/app/database/migrations/2015_02_05_102951_alter_table_products_drop_unique_index_product_code_upc_code.php',
    'AlterTableProductsRelatedAddStatus' => $baseDir . '/app/database/migrations/2014_12_30_000829_alter_table_products_related_add_status.php',
    'AlterTablePromotionRulesAddCumulativeColumn' => $baseDir . '/app/database/migrations/2015_01_13_032218_alter_table_promotion_rules_add_cumulative_column.php',
    'AlterTablePromotionsChangeColumnsToNull' => $baseDir . '/app/database/migrations/2015_02_04_090532_alter_table_promotions_change_columns_to_null.php',
    'AlterTableSettingsFixSettingMissingT' => $baseDir . '/app/database/migrations/2015_02_24_023207_alter_table_settings_fix_setting_missing_t.php',
    'AlterTableTransactionAddcolumnCurrency' => $baseDir . '/app/database/migrations/2015_04_06_132941_alter_table_transaction_addcolumn_currency.php',
    'AlterTableTransactionModifyTransactionid' => $baseDir . '/app/database/migrations/2015_01_06_094006_alter_table_transaction_modify_transactionid.php',
    'AlterTableTransactiondetailAddcolumnCurrency' => $baseDir . '/app/database/migrations/2015_04_06_155025_alter_table_transactiondetail_addcolumn_currency.php',
    'AlterTableTransactionsAddStatus' => $baseDir . '/app/database/migrations/2015_01_15_041119_alter_table_transactions_add_status.php',
    'AlterTableTransactionsAddTimestamp' => $baseDir . '/app/database/migrations/2014_12_30_082900_alter_table_transactions_add_timestamp.php',
    'AlterTableTransactionsAddnewcolumns' => $baseDir . '/app/database/migrations/2015_01_15_093649_alter_table_transactions_addnewcolumns.php',
    'AlterTableUserAddPhone2SectorOfActivityEtc' => $baseDir . '/app/database/migrations/2015_01_16_100801_alter_table_user_add_phone2_sector_of_activity_etc.php',
    'AlterTableUserDetailsR201411141745' => $baseDir . '/app/database/migrations/2014_11_14_111412_alter_table_user_details_r20141114_1745.php',
    'AlterTableWidgetsAddOrderAndAnimation' => $baseDir . '/app/database/migrations/2015_01_20_023957_alter_table_widgets_add_order_and_animation.php',
    'AlterTableWidgetsRenameWidgetsId' => $baseDir . '/app/database/migrations/2015_01_20_064318_alter_table_widgets_rename_widgets_id.php',
    'AlterTblUserDetailsAddPK' => $baseDir . '/app/database/migrations/2014_10_23_025206_alter_tbl_user_details_add_PK.php',
    'AlterTransactionDetailtaxesTaxvalueDecimalpoint' => $baseDir . '/app/database/migrations/2015_03_23_110209_alter_transaction_detailtaxes_taxvalue_decimalpoint.php',
    'AlterUserAddRememberTokenColumn' => $baseDir . '/app/database/migrations/2014_10_25_110811_alter_user_add_remember_token_column.php',
    'AlterUserDetailsAddNewColumnsAndIndexes' => $baseDir . '/app/database/migrations/2014_10_22_042320_alter_user_details_add_new_columns_and_indexes.php',
    'AlterUserDetailsAddPhoneAndPhotoColumn' => $baseDir . '/app/database/migrations/2014_10_24_195220_alter_user_details_add_phone_and_photo_column.php',
    'AlterUserDetailsAddRetailerId' => $baseDir . '/app/database/migrations/2014_12_24_045024_alter_user_details_add_retailer_id.php',
    'AlterUserDetailsChangePhone1Phone' => $baseDir . '/app/database/migrations/2015_01_19_022906_alter_user_details_change_phone1_phone.php',
    'AlterUsersColumnAccordingTo30Oct2014Changes' => $baseDir . '/app/database/migrations/2014_10_30_063154_alter_users_column_according_to_30-Oct-2014_changes.php',
    'AlterAllUseUuids' => $baseDir . '/app/database/migrations/2015_07_09_072123_alter_all_use_uuids.php',
    'AlterTableEmployeeRetailerDropTimestampColumns' => $baseDir . '/app/database/migrations/2015_08_19_135501_alter_table_employee_retailer_drop_timestamp_columns.php',
    'Apikey' => $baseDir . '/app/models/Apikey.php',
    'Arrays\\Util\\DuplicateChecker' => $baseDir . '/app/helpers/Arrays/Util/DuplicateChecker.php',
    'BaseController' => $baseDir . '/app/controllers/BaseController.php',
    'Cart' => $baseDir . '/app/models/Cart.php',
    'CartCoupon' => $baseDir . '/app/models/CartCoupon.php',
    'CartDetail' => $baseDir . '/app/models/CartDetail.php',
    'CashierAPIController' => $baseDir . '/app/controllers/api/v1/CashierAPIController.php',
    'Category' => $baseDir . '/app/models/Category.php',
    'CategoryAPIController' => $baseDir . '/app/controllers/api/v1/CategoryAPIController.php',
    'ChangeTableMerchantAddLocationIdAndType' => $baseDir . '/app/database/migrations/2015_07_08_062903_change_table_merchant_add_location_id_and_type.php',
    'ChangeTablePromotionAddMallIdAndAllRetailer' => $baseDir . '/app/database/migrations/2015_07_09_024640_change_table_promotion_add_mall_id_and_all_retailer.php',
    'Country' => $baseDir . '/app/models/Country.php',
    'CountryAPIController' => $baseDir . '/app/controllers/api/v1/CountryAPIController.php',
    'CountryTableSeeder' => $baseDir . '/app/database/seeds/CountryTableSeeder.php',
    'Coupon' => $baseDir . '/app/models/Coupon.php',
    'CouponAPIController' => $baseDir . '/app/controllers/api/v1/CouponAPIController.php',
    'CouponRetailer' => $baseDir . '/app/models/CouponRetailer.php',
    'CouponRetailerRedeem' => $baseDir . '/app/models/CouponRetailerRedeem.php',
    'CouponRule' => $baseDir . '/app/models/CouponRule.php',
    'CreatePersonalInterests' => $baseDir . '/app/database/migrations/2015_01_16_143511_create_personal_interests.php',
    'CreateSessionTable' => $baseDir . '/app/database/migrations/2015_04_22_093755_create_session_table.php',
    'CreateTableActivities' => $baseDir . '/app/database/migrations/2015_01_25_012015_create_table_activities.php',
    'CreateTableApikeys' => $baseDir . '/app/database/migrations/2014_10_14_083536_create_table_apikeys.php',
    'CreateTableCartCoupons' => $baseDir . '/app/database/migrations/2015_01_22_020809_create_table_cart_coupons.php',
    'CreateTableCartDetails' => $baseDir . '/app/database/migrations/2015_01_05_030410_create_table_cart_details.php',
    'CreateTableCarts' => $baseDir . '/app/database/migrations/2015_01_05_030015_create_table_carts.php',
    'CreateTableCategories' => $baseDir . '/app/database/migrations/2014_11_13_094502_create_table_categories.php',
    'CreateTableCountries' => $baseDir . '/app/database/migrations/2015_02_04_083950_create_table_countries.php',
    'CreateTableCoupons' => $baseDir . '/app/database/migrations/2014_12_22_093451_create_table_coupons.php',
    'CreateTableCustomPermission' => $baseDir . '/app/database/migrations/2014_10_14_083119_create_table_custom_permission.php',
    'CreateTableEmployeeRetailer' => $baseDir . '/app/database/migrations/2015_01_21_115319_create_table_employee_retailer.php',
    'CreateTableEmployees' => $baseDir . '/app/database/migrations/2015_01_21_063305_create_table_employees.php',
    'CreateTableEventRetailer' => $baseDir . '/app/database/migrations/2015_01_21_041709_create_table_event_retailer.php',
    'CreateTableEvents' => $baseDir . '/app/database/migrations/2015_01_21_040516_create_table_events.php',
    'CreateTableIssuedCoupons' => $baseDir . '/app/database/migrations/2015_01_12_072523_create_table_issued_coupons.php',
    'CreateTableMedia' => $baseDir . '/app/database/migrations/2014_12_08_020027_create_table_media.php',
    'CreateTableMerchantTaxes' => $baseDir . '/app/database/migrations/2014_12_03_032435_create_table_merchant_taxes.php',
    'CreateTableMerchants' => $baseDir . '/app/database/migrations/2014_11_07_020430_create_table_merchants.php',
    'CreateTablePermissionRole' => $baseDir . '/app/database/migrations/2014_10_14_063413_create_table_permission_role.php',
    'CreateTablePermissions' => $baseDir . '/app/database/migrations/2014_10_14_064030_create_table_permissions.php',
    'CreateTablePosQuickProducts' => $baseDir . '/app/database/migrations/2015_01_25_061213_create_table_pos_quick_products.php',
    'CreateTableProductAttributeValues' => $baseDir . '/app/database/migrations/2014_12_29_040955_create_table_product_attribute_values.php',
    'CreateTableProductAttributes' => $baseDir . '/app/database/migrations/2014_12_29_033808_create_table_product_attributes.php',
    'CreateTableProductCategory' => $baseDir . '/app/database/migrations/2014_11_13_095434_create_table_product_category.php',
    'CreateTableProductRetailer' => $baseDir . '/app/database/migrations/2014_12_05_055529_create_table_product_retailer.php',
    'CreateTableProductVariants' => $baseDir . '/app/database/migrations/2014_12_29_045536_create_table_product_variants.php',
    'CreateTableProducts' => $baseDir . '/app/database/migrations/2014_11_13_093845_create_table_products.php',
    'CreateTablePromotionRetailer' => $baseDir . '/app/database/migrations/2014_12_22_093250_create_table_promotion_retailer.php',
    'CreateTablePromotionRetailerRedeem' => $baseDir . '/app/database/migrations/2014_12_22_093425_create_table_promotion_retailer_redeem.php',
    'CreateTablePromotionRules' => $baseDir . '/app/database/migrations/2014_12_22_093159_create_table_promotion_rules.php',
    'CreateTablePromotions' => $baseDir . '/app/database/migrations/2014_12_22_081742_create_table_promotions.php',
    'CreateTableRoles' => $baseDir . '/app/database/migrations/2014_10_14_061616_create_table_roles.php',
    'CreateTableSettings' => $baseDir . '/app/database/migrations/2015_02_23_030735_create_table_settings.php',
    'CreateTableTokens' => $baseDir . '/app/database/migrations/2014_12_09_110021_create_table_tokens.php',
    'CreateTableTransactionDetailCoupons' => $baseDir . '/app/database/migrations/2015_01_29_044235_create_table_transaction_detail_coupons.php',
    'CreateTableTransactionDetailPromotions' => $baseDir . '/app/database/migrations/2015_01_29_024051_create_table_transaction_detail_promotions.php',
    'CreateTableTransactionDetailTaxes' => $baseDir . '/app/database/migrations/2015_02_12_063335_create_table_transaction_detail_taxes.php',
    'CreateTableTransactionDetails' => $baseDir . '/app/database/migrations/2014_12_30_072514_create_table_transaction_details.php',
    'CreateTableTransactions' => $baseDir . '/app/database/migrations/2014_12_30_061111_create_table_transactions.php',
    'CreateTableUserDetails' => $baseDir . '/app/database/migrations/2014_10_20_031134_create_table_user_details.php',
    'CreateTableUserPersonalInterest' => $baseDir . '/app/database/migrations/2015_01_16_140032_create_table_user_personal_interest.php',
    'CreateTableUsers' => $baseDir . '/app/database/migrations/2014_10_14_055729_create_table_users.php',
    'CreateTableWidgetRetailer' => $baseDir . '/app/database/migrations/2015_01_19_125915_create_table_widget_retailer.php',
    'CreateTableWidgets' => $baseDir . '/app/database/migrations/2015_01_19_122954_create_table_widgets.php',
    'CustomPermission' => $baseDir . '/app/models/CustomPermission.php',
    'Customerportal\\CustomerportalAPIController' => $baseDir . '/app/controllers/Customerportal/CustomerportalAPIController.php',
    'DashboardAPIController' => $baseDir . '/app/controllers/api/v1/DashboardAPIController.php',
    'DatabaseSeeder' => $baseDir . '/app/database/seeds/DatabaseSeeder.php',
    'DropTableCoupons' => $baseDir . '/app/database/migrations/2015_01_12_072412_drop_table_coupons.php',
    'DropTableProductCategory' => $baseDir . '/app/database/migrations/2014_12_29_060547_drop_table_product_category.php',
    'DummyAPIController' => $baseDir . '/app/controllers/api/v1/DummyAPIController.php',
    'Employee' => $baseDir . '/app/models/Employee.php',
    'EmployeeAPIController' => $baseDir . '/app/controllers/api/v1/EmployeeAPIController.php',
    'EventAPIController' => $baseDir . '/app/controllers/api/v1/EventAPIController.php',
    'EventModel' => $baseDir . '/app/models/EventModel.php',
    'EventRetailer' => $baseDir . '/app/models/EventRetailer.php',
    'Helper\\EloquentRecordCounter' => $baseDir . '/app/models/Helper/EloquentRecordCounter.php',
    'HomeController' => $baseDir . '/app/controllers/HomeController.php',
    'IlluminateQueueClosure' => $vendorDir . '/laravel/framework/src/Illuminate/Queue/IlluminateQueueClosure.php',
    'ImportAPIController' => $baseDir . '/app/controllers/api/v1/ImportAPIController.php',
    'IntermediateAuthBrowserController' => $baseDir . '/app/controllers/intermediate/v1/IntermediateAuthBrowserController.php',
    'IntermediateAuthController' => $baseDir . '/app/controllers/intermediate/v1/IntermediateAuthController.php',
    'IntermediateBaseController' => $baseDir . '/app/controllers/intermediate/v1/IntermediateBaseController.php',
    'IntermediateLoginController' => $baseDir . '/app/controllers/intermediate/v1/IntermediateLoginController.php',
    'IssuedCoupon' => $baseDir . '/app/models/IssuedCoupon.php',
    'IssuedCouponAPIController' => $baseDir . '/app/controllers/api/v1/IssuedCouponAPIController.php',
    'LoginAPIController' => $baseDir . '/app/controllers/api/v1/LoginAPIController.php',
    'Maatwebsite\\Excel\\Classes\\Cache' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Classes/Cache.php',
    'Maatwebsite\\Excel\\Classes\\FormatIdentifier' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Classes/FormatIdentifier.php',
    'Maatwebsite\\Excel\\Classes\\LaravelExcelWorksheet' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Classes/LaravelExcelWorksheet.php',
    'Maatwebsite\\Excel\\Classes\\PHPExcel' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Classes/PHPExcel.php',
    'Maatwebsite\\Excel\\Collections\\CellCollection' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Collections/CellCollection.php',
    'Maatwebsite\\Excel\\Collections\\ExcelCollection' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Collections/ExcelCollection.php',
    'Maatwebsite\\Excel\\Collections\\RowCollection' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Collections/RowCollection.php',
    'Maatwebsite\\Excel\\Collections\\SheetCollection' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Collections/SheetCollection.php',
    'Maatwebsite\\Excel\\Excel' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Excel.php',
    'Maatwebsite\\Excel\\ExcelServiceProvider' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/ExcelServiceProvider.php',
    'Maatwebsite\\Excel\\Exceptions\\LaravelExcelException' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Exceptions/LaravelExcelException.php',
    'Maatwebsite\\Excel\\Facades\\Excel' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Facades/Excel.php',
    'Maatwebsite\\Excel\\Files\\ExcelFile' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Files/ExcelFile.php',
    'Maatwebsite\\Excel\\Files\\ExportHandler' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Files/ExportHandler.php',
    'Maatwebsite\\Excel\\Files\\File' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Files/File.php',
    'Maatwebsite\\Excel\\Files\\ImportHandler' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Files/ImportHandler.php',
    'Maatwebsite\\Excel\\Files\\NewExcelFile' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Files/NewExcelFile.php',
    'Maatwebsite\\Excel\\Filters\\ChunkReadFilter' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Filters/ChunkReadFilter.php',
    'Maatwebsite\\Excel\\Parsers\\CssParser' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Parsers/CssParser.php',
    'Maatwebsite\\Excel\\Parsers\\ExcelParser' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Parsers/ExcelParser.php',
    'Maatwebsite\\Excel\\Parsers\\ViewParser' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Parsers/ViewParser.php',
    'Maatwebsite\\Excel\\Readers\\Batch' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Readers/Batch.php',
    'Maatwebsite\\Excel\\Readers\\ConfigReader' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Readers/ConfigReader.php',
    'Maatwebsite\\Excel\\Readers\\Html' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Readers/HtmlReader.php',
    'Maatwebsite\\Excel\\Readers\\LaravelExcelReader' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Readers/LaravelExcelReader.php',
    'Maatwebsite\\Excel\\Writers\\CellWriter' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Writers/CellWriter.php',
    'Maatwebsite\\Excel\\Writers\\LaravelExcelWriter' => $vendorDir . '/maatwebsite/excel/src/Maatwebsite/Excel/Writers/LaravelExcelWriter.php',
    'Media' => $baseDir . '/app/models/Media.php',
    'Merchant' => $baseDir . '/app/models/Merchant.php',
    'MerchantAPIController' => $baseDir . '/app/controllers/api/v1/MerchantAPIController.php',
    'MerchantDataSeeder' => $baseDir . '/app/database/seeds/MerchantDataSeeder.php',
    'MerchantRetailerScope' => $baseDir . '/app/models/MerchantRetailerScope.php',
    'MerchantTax' => $baseDir . '/app/models/MerchantTax.php',
    'MerchantTaxAPIController' => $baseDir . '/app/controllers/api/v1/MerchantTaxAPIController.php',
    'MerchantTypeTrait' => $baseDir . '/app/models/MerchantTypeTrait.php',
    'MobileCI\\MobileCIAPIController' => $baseDir . '/app/controllers/MobileCI/MobileCIAPIController.php',
    'ModelStatusTrait' => $baseDir . '/app/models/ModelStatusTrait.php',
    'Net\\Security\\Firewall' => $baseDir . '/app/helpers/Net/Security/Firewall.php',
    'OrbitRelation\\BelongsTo' => $baseDir . '/app/models/OrbitRelation/BelongsTo.php',
    'OrbitRelation\\HasManyThrough' => $baseDir . '/app/models/OrbitRelation/HasManyThrough.php',
    'OrbitTestCase' => $baseDir . '/app/tests/OrbitTestCase.php',
    'OrbitVersionAPIController' => $baseDir . '/app/controllers/OrbitVersionAPIController.php',
    'Orbit\\Builder' => $baseDir . '/app/helpers/Orbit/Builder.php',
    'Orbit\\OS\\Shutdown' => $baseDir . '/app/helpers/Orbit/OS/Shutdown.php',
    'Orbit\\Pagination' => $baseDir . '/app/helpers/Orbit/Pagination.php',
    'Orbit\\Setting' => $baseDir . '/app/helpers/Orbit/Setting.php',
    'Orbit\\Text' => $baseDir . '/app/helpers/Orbit/Text.php',
    'Orbit\\EncodedUUID' => $baseDir . '/app/helpers/Orbit/EncodedUUID.php',
    'POS\\CashierAPIController' => $baseDir . '/app/controllers/POS/CashierAPIController.php',
    'POS\\Product' => $baseDir . '/app/models/POS/Product.php',
    'Permission' => $baseDir . '/app/models/Permission.php',
    'PermissionRole' => $baseDir . '/app/models/PermissionRole.php',
    'PermissionRoleTableSeeder' => $baseDir . '/app/database/seeds/PermissionRoleTableSeeder.php',
    'PermissionTableSeeder' => $baseDir . '/app/database/seeds/PermissionTableSeeder.php',
    'PersonalInterest' => $baseDir . '/app/models/PersonalInterest.php',
    'PersonalInterestAPIController' => $baseDir . '/app/controllers/api/v1/PersonalInterestAPIController.php',
    'PersonalInterestTableSeeder' => $baseDir . '/app/database/seeds/PersonalInterestTableSeeder.php',
    'PosQuickProduct' => $baseDir . '/app/models/PosQuickProduct.php',
    'PosQuickProductAPIController' => $baseDir . '/app/controllers/api/v1/PosQuickProductAPIController.php',
    'Product' => $baseDir . '/app/models/Product.php',
    'ProductAPIController' => $baseDir . '/app/controllers/api/v1/ProductAPIController.php',
    'ProductAttribute' => $baseDir . '/app/models/ProductAttribute.php',
    'ProductAttributeAPIController' => $baseDir . '/app/controllers/api/v1/ProductAttributeAPIController.php',
    'ProductAttributeValue' => $baseDir . '/app/models/ProductAttributeValue.php',
    'ProductRetailer' => $baseDir . '/app/models/ProductRetailer.php',
    'ProductVariant' => $baseDir . '/app/models/ProductVariant.php',
    'Promotion' => $baseDir . '/app/models/Promotion.php',
    'PromotionAPIController' => $baseDir . '/app/controllers/api/v1/PromotionAPIController.php',
    'PromotionCouponScope' => $baseDir . '/app/models/PromotionCouponScope.php',
    'PromotionCouponTrait' => $baseDir . '/app/models/PromotionCouponTrait.php',
    'PromotionRetailer' => $baseDir . '/app/models/PromotionRetailer.php',
    'PromotionRule' => $baseDir . '/app/models/PromotionRule.php',
    'Report\\CashierPrinterController' => $baseDir . '/app/controllers/Report/CashierPrinterController.php',
    'Report\\ConsumerPrinterController' => $baseDir . '/app/controllers/Report/ConsumerPrinterController.php',
    'Report\\CouponPrinterController' => $baseDir . '/app/controllers/Report/CouponPrinterController.php',
    'Report\\DashboardPrinterController' => $baseDir . '/app/controllers/Report/DashboardPrinterController.php',
    'Report\\DataPrinterController' => $baseDir . '/app/controllers/Report/DataPrinterController.php',
    'Report\\DatabaseSimulationPrinterController' => $baseDir . '/app/controllers/Report/DatabaseSimulationPrinterController.php',
    'Report\\EventPrinterController' => $baseDir . '/app/controllers/Report/EventPrinterController.php',
    'Report\\MerchantPrinterController' => $baseDir . '/app/controllers/Report/MerchantPrinterController.php',
    'Report\\ProductPrinterController' => $baseDir . '/app/controllers/Report/ProductPrinterController.php',
    'Report\\PromotionPrinterController' => $baseDir . '/app/controllers/Report/PromotionPrinterController.php',
    'Report\\RetailerPrinterController' => $baseDir . '/app/controllers/Report/RetailerPrinterController.php',
    'Report\\TransactionHistoryPrinterController' => $baseDir . '/app/controllers/Report/TransactionHistoryPrinterController.php',
    'Retailer' => $baseDir . '/app/models/Retailer.php',
    'RetailerAPIController' => $baseDir . '/app/controllers/api/v1/RetailerAPIController.php',
    'Role' => $baseDir . '/app/models/Role.php',
    'RoleAPIController' => $baseDir . '/app/controllers/api/v1/RoleAPIController.php',
    'RoleTableSeeder' => $baseDir . '/app/database/seeds/RoleTableSeeder.php',
    'SessionAPIController' => $baseDir . '/app/controllers/api/v1/SessionAPIController.php',
    'SessionHandlerInterface' => $vendorDir . '/symfony/http-foundation/Symfony/Component/HttpFoundation/Resources/stubs/SessionHandlerInterface.php',
    'Setting' => $baseDir . '/app/models/Setting.php',
    'SettingAPIController' => $baseDir . '/app/controllers/api/v1/SettingAPIController.php',
    'SettingTableSeeder' => $baseDir . '/app/database/seeds/SettingTableSeeder.php',
    'ShutdownAPIController' => $baseDir . '/app/controllers/api/v1/ShutdownAPIController.php',
    'TestCase' => $baseDir . '/app/tests/TestCase.php',
    'Text\\Util\\LineChecker' => $baseDir . '/app/helpers/Text/Util/LineChecker.php',
    'Token' => $baseDir . '/app/models/Token.php',
    'TokenAPIController' => $baseDir . '/app/controllers/api/v1/TokenAPIController.php',
    'Transaction' => $baseDir . '/app/models/Transaction.php',
    'TransactionDetail' => $baseDir . '/app/models/TransactionDetail.php',
    'TransactionDetailCoupon' => $baseDir . '/app/models/TransactionDetailCoupon.php',
    'TransactionDetailPromotion' => $baseDir . '/app/models/TransactionDetailPromotion.php',
    'TransactionDetailTax' => $baseDir . '/app/models/TransactionDetailTax.php',
    'TransactionHistoryAPIController' => $baseDir . '/app/controllers/api/v1/TransactionHistoryAPIController.php',
    'UploadAPIController' => $baseDir . '/app/controllers/api/v1/UploadAPIController.php',
    'User' => $baseDir . '/app/models/User.php',
    'UserAPIController' => $baseDir . '/app/controllers/api/v1/UserAPIController.php',
    'UserDetail' => $baseDir . '/app/models/UserDetail.php',
    'UserRoleTrait' => $baseDir . '/app/models/UserRoleTrait.php',
    'UserTableSeeder' => $baseDir . '/app/database/seeds/UserTableSeeder.php',
    'Whoops\\Module' => $vendorDir . '/filp/whoops/src/deprecated/Zend/Module.php',
    'Whoops\\Provider\\Zend\\ExceptionStrategy' => $vendorDir . '/filp/whoops/src/deprecated/Zend/ExceptionStrategy.php',
    'Whoops\\Provider\\Zend\\RouteNotFoundStrategy' => $vendorDir . '/filp/whoops/src/deprecated/Zend/RouteNotFoundStrategy.php',
    'Widget' => $baseDir . '/app/models/Widget.php',
    'WidgetAPIController' => $baseDir . '/app/controllers/api/v1/WidgetAPIController.php',
    'WidgetRetailer' => $baseDir . '/app/models/WidgetRetailer.php',
    'merchantActivation' => $baseDir . '/app/commands/merchantActivation.php',
);
