<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableCustomPermission extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('custom_permission', function(Blueprint $table)
		{
			$table->bigInteger('custom_permission_id')->unsigned();
			$table->bigInteger('user_id')->unsigned();
			$table->integer('permission_id')->unsigned();
			$table->string('allowed',3);
			$table->timestamps();
			$table->primary(array('custom_permission_id'));
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('custom_permission');
	}

}
