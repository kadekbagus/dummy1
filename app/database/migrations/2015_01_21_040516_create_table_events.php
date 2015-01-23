<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableEvents extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->increments('event_id');
            $table->integer('merchant_id')->unsigned();
            $table->string('event_name', 255);
            $table->string('event_type', 15);
            $table->string('description', 2000)->nullable();
            $table->datetime('begin_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->char('is_permanent', 1)->nullable()->default('N');
            $table->string('status', 15);
            $table->string('image', 255)->nullable();
            $table->string('link_object_type', 50)->nullable();
            $table->bigInteger('link_object_id1')->unsigned()->nullable();
            $table->bigInteger('link_object_id2')->unsigned()->nullable();
            $table->bigInteger('link_object_id3')->unsigned()->nullable();
            $table->bigInteger('link_object_id4')->unsigned()->nullable();
            $table->bigInteger('link_object_id5')->unsigned()->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();
            $table->timestamps();

            $table->index(array('merchant_id'), 'merchant_id_idx');
            $table->index(array('event_name'), 'event_name_idx');
            $table->index(array('event_type'), 'event_type_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('begin_date', 'end_date'), 'begindate_enddate_idx');
            $table->index(array('link_object_type'), 'link_object_type_idx');
            $table->index(array('link_object_id1'), 'link_object_id1_idx');
            $table->index(array('link_object_id2'), 'link_object_id2_idx');
            $table->index(array('link_object_id3'), 'link_object_id3_idx');
            $table->index(array('link_object_id4'), 'link_object_id4_idx');
            $table->index(array('link_object_id5'), 'link_object_id5_idx');
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
        Schema::drop('events');
    }

}
