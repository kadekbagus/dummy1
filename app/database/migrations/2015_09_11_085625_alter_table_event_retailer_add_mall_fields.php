<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEventRetailerAddMallFields extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('event_retailer', function(Blueprint $table)
		{
			$table->string('object_type', 50)->nullable()->after('retailer_id');
			$table->index(['object_type'], 'object_type_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('event_retailer', function(Blueprint $table)
		{
			$table->dropColumn('object_type');
		});
	}

}
