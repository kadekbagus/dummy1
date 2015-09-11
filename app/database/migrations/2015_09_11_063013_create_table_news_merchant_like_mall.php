<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableNewsMerchantLikeMall extends Migration {

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
		$builder->create('news_merchant', function(OrbitBlueprint $table)
		{
            $table->engine = 'InnoDB';
            $table->encodedId('news_merchant_id');
            $table->encodedId('news_id');
            $table->encodedId('merchant_id');
            $table->string('object_type', 15)->nullable();

            $table->primary('news_merchant_id');
            $table->index(array('news_id'), 'news_id_idx');
            $table->index(array('merchant_id'), 'merchant_id_idx');
            $table->index(array('object_type'), 'object_type_idx');

		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('news_merchant');
	}

}
