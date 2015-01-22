<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableEventRetailer extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_retailer', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->increments('event_retailer_id');
            $table->integer('event_id')->unsigned();
            $table->integer('retailer_id')->unsigned();
            $table->timestamps();

            $table->index(array('event_id'), 'event_id_idx');
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
        Schema::drop('event_retailer');
    }

}
