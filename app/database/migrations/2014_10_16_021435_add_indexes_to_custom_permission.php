<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToCustomPermission extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('custom_permission', function(Blueprint $table)
		{
			$table->index(array('user_id'), 'user_id_idx');
            $table->index(array('permission_id'), 'permission_id_idx');
            $table->index(array('user_id', 'permission_id'), 'user_perm_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('custom_permission', function(Blueprint $table)
		{
			$table->dropIndex('user_id_idx');
            $table->dropIndex('permission_id_idx');
            $table->dropIndex('user_perm_idx');
		});
	}

}
