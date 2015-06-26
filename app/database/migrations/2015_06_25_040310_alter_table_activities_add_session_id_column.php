<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableActivitiesAddSessionIdColumn extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table("activities", function(Blueprint $table) {
            $table->string('session_id')->nullable()->after('ip_address');
            //$table->index(array('session_id'), 'session_id_idx');
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table("activities", function (Blueprint $table) {
            $table->dropColumn('session_id');
            //$table->dropIndex('session_id_idx');
        });
	}

}
