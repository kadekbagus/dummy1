<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableNewsLikeMall extends Migration {

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

		$builder->create('news', function(OrbitBlueprint $table)
		{


            $table->engine = 'InnoDB';
            $table->encodedId('news_id');
            $table->encodedId('mall_id');
            $table->string('object_type', 15)->nullable();
            $table->string('news_name', 255);
            $table->string('description', 2000)->nullable();
            $table->string('image', 255)->nullable();
            $table->datetime('begin_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->tinyInteger('sticky_order')->nullable()->default('0');
            $table->string('link_object_type', 15)->nullable();
            $table->string('status', 15);
            $table->encodedId('created_by')->nullable();
            $table->encodedId('modified_by')->nullable();
            $table->timestamps();

            $table->primary('news_id');
            $table->index(array('mall_id'), 'mall_id_idx');
            $table->index(array('object_type'), 'object_type_idx');
            $table->index(array('news_name'), 'news_name_idx');
            $table->index(array('begin_date', 'end_date'), 'begindate_enddate_idx');
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
		Schema::drop('news');
	}

}
