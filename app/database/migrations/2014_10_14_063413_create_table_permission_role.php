<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTablePermissionRole extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('permission_role', function(Blueprint $table)
		{
			$table->bigInteger('permission_role_id')->unsigned();
			$table->integer('role_id')->unsigned();
			$table->integer('permission_id')->unsigned();
			$table->string('allowed',3);
			$table->timestamps();
			$table->primary(array('permission_role_id'));
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('permission_role');
	}

}
