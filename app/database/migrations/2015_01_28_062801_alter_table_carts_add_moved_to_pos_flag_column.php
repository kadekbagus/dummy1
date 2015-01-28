<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableCartsAddMovedToPosFlagColumn extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('carts', function(Blueprint $table)
		{
			$table->char('moved_to_pos', 1)->nullable()->default('N')->after('status');
			$table->index(array('moved_to_pos'), 'moved_to_pos_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('carts', function(Blueprint $table)
		{
			$table->dropIndex('moved_to_pos_idx');
			$table->dropColumn('moved_to_pos');
		});
	}

}
