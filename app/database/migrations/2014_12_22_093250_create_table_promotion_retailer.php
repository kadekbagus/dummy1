<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTablePromotionRetailer extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotion_retailer', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->increments('promotion_retailer_id');
            $table->integer('promotion_id')->unsigned();
            $table->integer('retailer_id')->unsigned();
            $table->timestamps();

            $table->index(array('promotion_id'), 'promotion_id_idx');
            $table->index(array('retailer_id'), 'retailer_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('promotion_retailer');
    }

}
