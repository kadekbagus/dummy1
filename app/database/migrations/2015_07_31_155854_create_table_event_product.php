<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableEventProduct extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('event_product', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('event_product_id');
            $table->bigInteger('event_id')->unsigned();
            $table->bigInteger('product_id')->unsigned();
            $table->timestamps();

            $table->index(array('event_id'), 'event_id_idx');
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
		Schema::drop('event_product');
	}

}
