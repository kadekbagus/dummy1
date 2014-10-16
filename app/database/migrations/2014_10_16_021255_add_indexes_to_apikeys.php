<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToApikeys extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('apikeys', function(Blueprint $table)
		{
			$table->index(array('api_key'), 'api_key_idx');
            $table->index(array('user_id'), 'user_id_idx');
            $table->index(array('api_key', 'user_id'), 'api_key_user_idx');
            $table->index(array('api_key', 'status'), 'api_key_status_idx');
            $table->index(array('api_key', 'user_id', 'status'), 'api_key_user_status_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('apikeys', function(Blueprint $table)
		{
			$table->dropIndex('api_key_idx');
            $table->dropIndex('user_id_idx');
            $table->dropIndex('api_key_user_idx');
            $table->dropIndex('api_key_status_idx');
            $table->dropIndex('api_key_user_status_idx');
		});
	}

}
