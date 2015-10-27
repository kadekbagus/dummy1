<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableObjectsLikeMall extends Migration {

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

		$builder->create('objects', function(OrbitBlueprint $table)
		{
            $table->engine = 'InnoDB';
            $table->encodedId('object_id');
            $table->encodedId('merchant_id');
            $table->string('object_name', 50);
            $table->string('object_type', 50)->nullable();
            $table->string('status', 15);
            $table->timestamps();

            $table->primary('object_id');
            $table->index(array('object_id'), 'object_id_idx');
            $table->index(array('merchant_id'), 'merchant_id_idx');
            $table->index(array('object_name'), 'object_name_idx');
            $table->index(array('object_type'), 'object_type_idx');
            $table->index(array('status'), 'status_idx');

		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('objects');
	}

}
