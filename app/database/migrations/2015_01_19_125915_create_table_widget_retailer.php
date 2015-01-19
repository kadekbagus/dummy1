<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableWidgetRetailer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('widget_retailer', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('widget_retailer_id');
            $table->bigInteger('widget_id')->unsigned();
            $table->bigInteger('retailer_id')->unsigned();
            $table->timestamps();

            $table->index(array('widget_id'), 'widgetid_idx');
            $table->index(array('retailer_id'), 'retailerid_idx');
            $table->index(array('widget_id', 'retailer_id'), 'widget_retailer_idx');
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
        Schema::drop('widget_retailer');
    }
}
