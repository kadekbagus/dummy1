<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToMerchants extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('merchants', function(Blueprint $table)
		{
			//
			$table->index(array('merchant_id'), 'merchantid_idx');
			$table->index(array('merchant_id', 'status'), 'merchantid_status_idx');
			$table->index(array('merchant_id', 'object_type', 'status'), 'merchantid_status_object_type_idx');
			$table->index(array('merchant_id', 'email'), 'merchantid_email_idx');
			$table->index(array('merchant_id', 'email', 'status'), 'merchantid_email_status_idx');
			$table->index(array('merchant_id', 'email', 'status', 'object_type'), 'merchantid_email_status_object_type_idx');
			$table->index(array('user_id'), 'merchant_userid_idx');
			$table->index(array('user_id', 'status'), 'merchant_userid_status_idx');
			$table->index(array('user_id', 'status', 'object_type'), 'merchant_userid_status_object_type_idx');
			$table->index(array('name'), 'merchant_name_idx');
			$table->index(array('status'), 'merchant_status_idx');
			$table->index(array('name', 'status'), 'merchant_name_status_idx');
			$table->index(array('name', 'status', 'object_type'), 'merchant_name_status_object_type_idx');
			$table->index(array('email', 'status'), 'merchant_email_status_idx');
			$table->index(array('email', 'status', 'object_type'), 'merchant_email_status_object_type_idx');
			$table->index(array('city_id'), 'merchant_cityid_idx');
			$table->index(array('city'), 'merchant_city_idx');
			$table->index(array('city_id', 'status'), 'merchant_cityid_status_idx');
			$table->index(array('city', 'status'), 'merchant_city_status_idx');
			$table->index(array('city_id', 'status', 'object_type'), 'merchant_cityid_status_object_type_idx');
			$table->index(array('city', 'status', 'object_type'), 'merchant_city_status_object_type_idx');
			$table->index(array('country'), 'merchant_country_idx');
			$table->index(array('country_id', 'status'), 'merchant_countryid_status_idx');
			$table->index(array('country', 'status'), 'merchant_country_status_idx');
			$table->index(array('country_id', 'status', 'object_type'), 'merchant_countryid_status_object_type_idx');
			$table->index(array('country', 'status', 'object_type'), 'merchant_country_status_object_type_idx');
			$table->index(array('parent_id'), 'merchant_parentid_idx');
			$table->index(array('parent_id', 'status'), 'merchant_parentid_status_idx');
			$table->index(array('parent_id', 'status', 'object_type'), 'merchant_parentid_status_object_type_idx');
			$table->index(array('start_date_activity'), 'merchant_start_date_activity_idx');
			$table->index(array('vat_included'), 'merchant_vat_included_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('merchants', function(Blueprint $table)
		{
			//
			$table->dropIndex('merchantid_idx');
			$table->dropIndex('merchantid_status_idx');
			$table->dropIndex('merchantid_status_object_type_idx');
			$table->dropIndex('merchantid_email_idx');
			$table->dropIndex('merchantid_email_status_idx');
			$table->dropIndex('merchantid_email_status_object_type_idx');
			$table->dropIndex('merchant_userid_idx');
			$table->dropIndex('merchant_userid_status_idx');
			$table->dropIndex('merchant_userid_status_object_type_idx');
			$table->dropIndex('merchant_name_idx');
			$table->dropIndex('merchant_status_idx');
			$table->dropIndex('merchant_name_status_idx');
			$table->dropIndex('merchant_name_status_object_type_idx');
			$table->dropIndex('merchant_email_status_idx');
			$table->dropIndex('merchant_email_status_object_type_idx');
			$table->dropIndex('merchant_cityid_idx');
			$table->dropIndex('merchant_city_idx');
			$table->dropIndex('merchant_cityid_status_idx');
			$table->dropIndex('merchant_city_status_idx');
			$table->dropIndex('merchant_cityid_status_object_type_idx');
			$table->dropIndex('merchant_city_status_object_type_idx');
			$table->dropIndex('merchant_country_idx');
			$table->dropIndex('merchant_countryid_status_idx');
			$table->dropIndex('merchant_country_status_idx');
			$table->dropIndex('merchant_countryid_status_object_type_idx');
			$table->dropIndex('merchant_country_status_object_type_idx');
			$table->dropIndex('merchant_parentid_idx');
			$table->dropIndex('merchant_parentid_status_idx');
			$table->dropIndex('merchant_parentid_status_object_type_idx');
			$table->dropIndex('merchant_start_date_activity_idx');
			$table->dropIndex('merchant_vat_included_idx');
		});
	}

}
