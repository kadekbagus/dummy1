<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableMerchantTaxes extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('merchant_taxes', function(Blueprint $table)
		{
			$table->increments('merchant_tax_id');
			$table->integer('merchant_id')->unsigned();
			$table->string('tax_name', 50);
			$table->decimal('tax_value', 5, 4)->default(0);
			$table->bigInteger('created_by')->unsigned()->nullable();
			$table->bigInteger('modified_by')->unsigned()->nullable();
			$table->timestamps();

			$table->index(array('merchant_id'), 'merchant_id_idx');
			$table->index(array('tax_name'), 'tax_name_idx');
			$table->index(array('merchant_id', 'tax_name'), 'merchantid_taxname_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('merchant_taxes');
	}

}
