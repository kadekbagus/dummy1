<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToUsers extends Migration 
{

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('users', function(Blueprint $table)
		{
			$table->index(array('username'), 'username_idx');
            $table->index(array('username', 'user_password'), 'username_pwd_idx');
            $table->index(array('user_email'), 'email_idx');
            $table->index(array('username', 'user_password', 'status'), 'username_pwd_status_idx');
            $table->index(array('user_ip'), 'user_ip_idx');
            $table->index(array('user_role_id'), 'user_role_id_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('created_at'), 'created_at_idx');
            $table->index(array('updated_at'), 'updated_at_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('users', function(Blueprint $table)
		{
			$table->dropIndex('username_idx');
            $table->dropIndex('username_pwd_idx');
            $table->dropIndex('email_idx');
            $table->dropIndex('username_pwd_status_idx');
            $table->dropIndex('user_ip_idx');
            $table->dropIndex('user_role_id_idx');
            $table->dropIndex('status_idx');
            $table->dropIndex('modified_by_idx');
            $table->dropIndex('created_at_idx');
            $table->dropIndex('updated_at_idx');
		});
	}

}
