<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableObjectRelationLikeMall extends Migration {

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

		$builder->create('object_relation', function(OrbitBlueprint $table)
		{
			$table->engine = 'InnoDB';
            $table->encodedId('object_relation_id');
            $table->encodedId('main_object_id');
            $table->string('main_object_type', 50)->nullable();
            $table->encodedId('secondary_object_id');
            $table->string('secondary_object_type', 50)->nullable();

            $table->primary('object_relation_id');
            $table->index(array('main_object_id'), 'main_object_id_idx');
            $table->index(array('main_object_type'), 'main_object_type_idx');
            $table->index(array('secondary_object_id'), 'secondary_object_id_idx');
            $table->index(array('secondary_object_type'), 'secondary_object_type_idx');

		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('object_relation');
	}

}
