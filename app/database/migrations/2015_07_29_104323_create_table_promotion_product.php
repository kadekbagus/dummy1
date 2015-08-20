<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTablePromotionProduct extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('promotion_product', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('promotion_product_id');
            $table->bigInteger('promotion_id')->unsigned();
            $table->bigInteger('product_id')->unsigned();
            $table->timestamps();

            $table->index(array('promotion_id'), 'promotion_id_idx');
            $table->index(array('product_id'), 'product_id_idx');
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('promotion_product');
	}

}
