<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableLuckyDrawNumbersLikeMall extends Migration {

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

		$builder->create('lucky_draw_numbers', function(OrbitBlueprint $table)
		{
			$table->encodedId('lucky_draw_number_id');
            $table->encodedId('lucky_draw_id');
            $table->encodedId('user_id')->nullable();
            $table->string('hash', 40)->nullable();
            $table->string('lucky_draw_number_code', 50);
            $table->datetime('issued_date')->nullable();
            $table->encodedId('created_by')->nullable();
            $table->encodedId('modified_by')->nullable();
			$table->string('status', 15)->nullable();
			$table->timestamps();

            $table->primary('lucky_draw_number_id');
            $table->unique(array('lucky_draw_id', 'lucky_draw_number_code', 'status'), 'luckydrawid_luckydrawnumbercode_status_unique');
            $table->index(array('lucky_draw_id'), 'lucky_draw_id_idx');
            $table->index(array('user_id'), 'user_id_idx');
            $table->index(array('lucky_draw_number_code'), 'lucky_draw_number_code_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('created_at'), 'created_at_idx');
            $table->index(array('hash'), 'hash_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('lucky_draw_numbers');
	}

}
