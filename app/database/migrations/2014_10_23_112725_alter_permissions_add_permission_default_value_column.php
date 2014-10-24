<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPermissionsAddPermissionDefaultValueColumn extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('permissions', function(Blueprint $table)
		{
			$table->string('permission_default_value', 3)->default('no')->after('permission_group_order');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('permissions', function(Blueprint $table)
		{
			$table->dropColumn('permission_default_value');
		});
	}

}
