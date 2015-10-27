<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddMallFields extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		$builder = DB::connection()->getSchemaBuilder();
		$builder->blueprintResolver(function ($table, $callback) {
			return new OrbitBlueprint($table, $callback);
		});

		$builder->table('merchants', function(OrbitBlueprint $table)
		{
			$table->string('is_mall', 3)->nullable()->default('no')->after('parent_id');
			$table->string('floor', 30)->nullable()->after('ticket_footer');
			$table->string('unit', 30)->nullable()->after('floor');
            $table->string('external_object_id', 50)->nullable()->after('unit');

			$table->index(['external_object_id'], 'external_object_id_idx');
            $table->index(array('is_mall', 'status', 'object_type'), 'is_mall_status_object_idx');
			$table->index(array('unit'), 'unit_idx');
		 	$table->index(array('floor'), 'floor_idx');
			$table->index(array('is_mall'), 'is_mall');
			$table->index(array('is_mall', 'status'), 'is_mall_status_idx');
			$table->index(array('is_mall', 'status', 'object_type'), 'is_mal_status_object_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('merchants', function(Blueprint $table)
		{
			$table->dropIndex('is_mall_status_object_idx');
			$table->dropIndex('is_mall_status_idx');
			$table->dropIndex('is_mall');
			$table->dropIndex('floor_idx');
			$table->dropIndex('unit_idx');
			$table->dropIndex('external_object_id_idx');

			$table->dropColumn('external_object_id');
			$table->dropColumn('unit');
			$table->dropColumn('is_mall');
			$table->dropColumn('floor');
		});
	}

}
