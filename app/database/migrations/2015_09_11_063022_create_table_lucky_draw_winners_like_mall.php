<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableLuckyDrawWinnersLikeMall extends Migration {

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
		$builder->create('lucky_draw_winners', function(OrbitBlueprint $table)
		{
            $table->engine = 'InnoDB';
            $table->encodedId('lucky_draw_winner_id');
            $table->encodedId('lucky_draw_id');
            $table->string('lucky_draw_winner_code', 50);
            $table->tinyInteger('position')->nullable()->default('1');
            $table->encodedId('lucky_draw_number_id')->nullable();
            $table->string('status', 15)->nullable();
            $table->encodedId('created_by')->nullable();
            $table->encodedId('modified_by')->nullable();
            $table->timestamps();

            $table->primary('lucky_draw_winner_id');
            $table->index(array('lucky_draw_id'), 'lucky_draw_id_idx');
            $table->index(array('lucky_draw_winner_code'), 'lucky_draw_winner_code_idx');
            $table->index(array('lucky_draw_number_id'), 'lucky_draw_number_id_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('created_at'), 'created_at_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('lucky_draw_winners');
	}

}
