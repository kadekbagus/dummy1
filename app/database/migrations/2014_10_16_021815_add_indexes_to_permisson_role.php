<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToPermissonRole extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('permission_role', function(Blueprint $table)
		{
			$table->index(array('role_id'), 'role_id_idx');
            $table->index(array('permission_id'), 'permission_id_idx');
            $table->index(array('role_id', 'permission_id'), 'role_perm_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('permission_role', function(Blueprint $table)
		{
			$table->dropIndex('role_id_idx');
            $table->dropIndex('permission_id_idx');
            $table->dropIndex('role_perm_idx');
		});
	}

}
