<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableProductCategory extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('product_category', function(Blueprint $table)
		{
			$table->increments('product_category_id')->unsigned();
			$table->integer('category_id')->unsigned();
			$table->bigInteger('product_id')->unsigned();
			$table->timestamps();
			$table->index(array('category_id'), 'categoryid_idx');
			$table->index(array('product_id'), 'productid_idx');
			$table->index(array('category_id', 'product_id'), 'categoryid_productid_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('product_category');
	}

}
