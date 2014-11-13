<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableCategories extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('categories', function(Blueprint $table)
		{
			$table->increments('category_id')->unsigned();
			$table->string('category_name', 100);
			$table->integer('parent_id')->unsigned();
			$table->integer('category_order')->unsigned()->nullable()->default(0);
			$table->string('description', 2000)->nullable();
			$table->string('status', 15)->nullable()->default('active');
			$table->bigInteger('modified_by')->unsigned()->nullable();
			$table->timestamps();
			$table->index(array('category_name'), 'category_name_idx');
			$table->index(array('parent_id'), 'parentid_idx');
			$table->index(array('category_order'), 'category_order_idx');
			$table->index(array('status'), 'status_idx');
			$table->index(array('modified_by'), 'modified_by_idx');
			$table->index(array('created_at'), 'created_at_idx');
			$table->index(array('updated_at'), 'updated_at_idx');
			$table->index(array('category_name', 'parent_id'), 'category_parentid_idx');
			$table->index(array('category_name', 'status'), 'category_status_idx');
			$table->index(array('category_name', 'category_order'), 'category_name_order_idx');
			$table->index(array('category_name', 'category_order', 'status'), 'category_name_order_status_idx');
			$table->index(array('category_order', 'status'), 'category_order_status_idx');
			$table->index(array('parent_id', 'category_order', 'status'), 'parentid_order_status_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('categories');
	}

}
