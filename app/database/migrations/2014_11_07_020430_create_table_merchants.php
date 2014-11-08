<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableMerchants extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('merchants', function(Blueprint $table)
		{
			$table->increments('merchant_id')->unsigned();
			$table->bigInteger('user_id')->unsigned();
			$table->string('email', 255);
			$table->string('name', 100)->nullable();
			$table->text('description')->nullable();
			$table->string('address_line1', 2000)->nullable();
			$table->string('address_line2', 2000)->nullable();
			$table->string('address_line3', 2000)->nullable();
			$table->integer('city_id')->unsigned()->nullable();
			$table->string('city', 100)->nullable();
			$table->integer('country_id')->unsigned()->nullable();
			$table->string('country', 100)->nullable();
			$table->string('phone', 50)->nullable();
			$table->string('fax', 50)->nullable();
			$table->dateTime('start_date_activity')->nullable();
			$table->string('status', 15)->nullable()->default('active');
			$table->string('logo', 255)->nullable();
			$table->char('currency', 3)->nullable()->default('USD');
			$table->char('currency_symbol', 3)->nullable()->default('$');
			$table->string('tax_code1', 15)->nullable();
			$table->string('tax_code2', 15)->nullable();
			$table->string('tax_code3', 15)->nullable();
			$table->text('slogan')->nullable();
			$table->char('vat_included', 3)->nullable()->default('yes');
			$table->string('object_type', 15)->nullable()->default('merchant');
			$table->integer('parent_id')->unsigned()->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('merchants');
	}

}
