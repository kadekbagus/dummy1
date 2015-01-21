<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableWidgets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('widgets', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('widgets_id');
            $table->string('widget_type', 50)->nullable();
            $table->bigInteger('widget_object_id')->nullable()->unsigned();
            $table->string('widget_slogan', 500)->nullable();
            $table->bigInteger('merchant_id')->nullable()->unsigned();
            $table->string('status', 15)->nullable()->default('active');
            $table->bigInteger('created_by')->nullable()->unsigned();
            $table->bigInteger('modified_by')->nullable()->unsigned();
            $table->timestamps();

            $table->index(array('widget_type'), 'widget_type_idx');
            $table->index(array('widget_object_id'), 'widget_objectid_idx');
            $table->index(array('merchant_id'), 'merchantid_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('created_at'), 'created_at_idx');

            $table->index(array('widget_type', 'widget_object_id', 'status'), 'type_object_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('widgets');
    }
}
