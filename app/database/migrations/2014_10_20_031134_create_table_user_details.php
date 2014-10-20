<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableUserDetails extends Migration
{
    /**
	 * Run the migrations.
	 *
	 * @return void
	 */
    public function up()
    {
        Schema::create('user_details', function (Blueprint $table) {
            $table->bigInteger('user_id');
            $table->integer('merchant_id')->unsigned()->nullable();
            $table->dateTime('merchant_acquired_date')->nullable();
            $table->string('address_line1', 2000)->nullable();
            $table->string('address_line2', 2000)->nullable();
            $table->string('address_line3', 2000)->nullable();
            $table->integer('postal_code')->unsigned()->nullable();
            $table->integer('city_id')->unsigned()->nullable();
            $table->string('city', 100)->nullable();
            $table->integer('country_id')->unsigned()->nullable();
            $table->string('country', 100)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('currency_symbol', 3)->nullable();
            $table->date('birthdate')->nullable();
            $table->char('gender', 1)->nullable();
            $table->string('relationship_status',10)->nullable();
            $table->integer('number_visit_all_shop')->unsigned()->nullable()->default(0);
            $table->decimal('amount_spent_all_shop', 16, 2)->nullable()->default(0.00);
            $table->decimal('average_spent_per_month_all_shop', 16, 2)->nullable()->default(0.00);
            $table->dateTime('last_visit_any_shop')->nullable();
            $table->integer('last_visit_shop_id')->unsigned()->nullable();
            $table->dateTime('last_purchase_any_shop')->nullable();
            $table->integer('last_purchase_shop_id')->unsigned()->nullable();
            $table->decimal('last_spent_any_shop', 16, 2)->nullable()->default(0.00);
            $table->integer('last_spent_shop_id')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();
            $table->timestamps();
            $table->index(array('user_id'), 'user_idx');
            $table->index(array('merchant_id'), 'merchant_id_idx');
            $table->index(array('user_id', 'merchant_id'), 'userid_merchantid_idx');
            $table->index(array('merchant_acquired_date'), 'merchant_acquired_date_idx');
            $table->index(array('user_id', 'merchant_id', 'merchant_acquired_date'), 'userid_merchantid_acquired_idx');
            $table->index(array('user_id', 'city'), 'userid_city_idx');
            $table->index(array('user_id', 'city_id'), 'userid_cityid_idx');
            $table->index(array('user_id', 'country'), 'userid_country_idx');
            $table->index(array('user_id', 'country_id'), 'userid_countryid_idx');
            $table->index(array('city_id'), 'city_id_idx');
            $table->index(array('city'), 'city_idx');
            $table->index(array('country_id'), 'country_id_idx');
            $table->index(array('country'), 'country_idx');
            $table->index(array('city_id', 'country_id'), 'cityid_countryid_idx');
            $table->index(array('city', 'country'), 'city_country_idx');
            $table->index(array('currency'), 'currency_idx');
            $table->index(array('birthdate'), 'birthdate_idx');
            $table->index(array('gender'), 'gender_idx');
            $table->index(array('relationship_status'), 'relationship_status_idx');
            $table->index(array('number_visit_all_shop'), 'number_visit_all_shop_idx');
            $table->index(array('number_visit_all_shop', 'city'), 'number_visit_city_idx');
            $table->index(array('number_visit_all_shop', 'city_id'), 'number_visit_cityid_idx');
            $table->index(array('number_visit_all_shop', 'gender'), 'number_visit_gender_idx');
            $table->index(array('amount_spent_all_shop'), 'amount_spent_all_shop_idx');
            $table->index(array('amount_spent_all_shop', 'city'), 'amount_spent_city_idx');
            $table->index(array('amount_spent_all_shop', 'city_id'), 'amount_spent_cityid_idx');
            $table->index(array('amount_spent_all_shop', 'gender'), 'amount_spent_gender_idx');
            $table->index(array('average_spent_per_month_all_shop'), 'average_spent_per_month_all_shop_idx');
            $table->index(array('average_spent_per_month_all_shop', 'city'), 'average_spent_city_idx');
            $table->index(array('average_spent_per_month_all_shop', 'city_id'), 'average_spent_cityid_idx');
            $table->index(array('average_spent_per_month_all_shop', 'gender'), 'average_spent_gender_idx');
            $table->index(array('last_visit_any_shop'), 'last_visit_any_shop_idx');
            $table->index(array('last_visit_shop_id'), 'last_visit_shop_id_idx');
            $table->index(array('last_purchase_any_shop'), 'last_purchase_any_shop_idx');
            $table->index(array('last_purchase_shop_id'), 'last_purchase_shop_id_idx');
            $table->index(array('last_spent_any_shop'), 'last_spent_any_shop_idx');
            $table->index(array('last_spent_shop_id'), 'last_spent_shop_id_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('created_at'), 'created_at_idx');
            $table->index(array('updated_at'), 'updated_at_idx');
        });
    }

    /**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
    public function down()
    {
        Schema::drop('user_details');
    }

}
