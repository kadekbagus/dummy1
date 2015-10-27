<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableCategoryMerchantLikeMall extends Migration {

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
		$builder->create('category_merchant', function(OrbitBlueprint $table)
		{
            $table->encodedId('category_merchant_id');
            $table->encodedId('category_id')->nullable()->default(NULL);
            $table->encodedId('merchant_id')->nullable()->default(NULL);
            $table->timestamps();

            $table->primary('category_merchant_id');
            $table->index(array('category_id'), 'category_idx');
            $table->index(array('merchant_id'), 'merchant_idx');
            $table->index(array('merchant_id'), 'category_merchant_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('category_merchant');
	}

}
