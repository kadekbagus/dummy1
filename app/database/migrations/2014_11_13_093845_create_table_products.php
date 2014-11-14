<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableProducts extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('products', function(Blueprint $table)
		{
			$table->bigIncrements('product_id')->unsigned();
			$table->string('product_code', 20);
			$table->string('product_name', 100);
			$table->decimal('price', 16, 2)->nullable()->default(0.00);
			$table->string('tax_code', 15)->nullable();
			$table->string('short_description', 2000)->nullable();
			$table->text('long_description')->nullable();
			$table->string('image', 255)->nullable();
			$table->char('is_new', 3)->nullable()->default('yes');
			$table->datetime('new_until')->nullable();
			$table->integer('stock')->unsigned()->nullable()->default(0);
			$table->char('depend_on_stock', 3)->nullable()->default('yes');
			$table->integer('retailer_id')->nullable();
			$table->integer('merchant_id')->nullable();
			$table->bigInteger('created_by')->nullable();
			$table->bigInteger('modified_by')->nullable();
			$table->string('status', 15)->nullable();
			$table->timestamps();
			$table->index(array('product_code'), 'product_code_idx');
			$table->index(array('product_name'), 'product_name_idx');
			$table->index(array('price'), 'price_idx');
			$table->index(array('tax_code'), 'tax_code_idx');
			$table->index(array('is_new'), 'is_new_idx');
			$table->index(array('new_until'), 'new_until_idx');
			$table->index(array('stock'), 'stock_idx');
			$table->index(array('depend_on_stock'), 'depend_on_stock_idx');
			$table->index(array('retailer_id'), 'retailerid_idx');
			$table->index(array('merchant_id'), 'merchantid_idx');
			$table->index(array('created_by'), 'created_by_idx');
			$table->index(array('modified_by'), 'modified_by_idx');
			$table->index(array('status'), 'status_idx');
			$table->index(array('price', 'status'), 'price_status_idx');
			$table->index(array('is_new', 'status'), 'is_new_status_idx');
			$table->index(array('new_until', 'status'), 'new_until_status_idx');
			$table->index(array('retailer_id', 'status'), 'retailerid_status_idx');
			$table->index(array('merchant_id', 'status'), 'merchantid_status_idx');
			$table->index(array('price', 'retailer_id', 'status'), 'price_retailerid_status_idx');
			$table->index(array('price', 'merchant_id', 'status'), 'price_merchantid_status_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('products');
	}

}
