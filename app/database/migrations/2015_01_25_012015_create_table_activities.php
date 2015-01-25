<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableActivities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('activities', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('activity_id');
            $table->string('activity_name', 100)->nullable();
            $table->string('activity_type', 50)->nullable();
            $table->bigInteger('user_id')->nullable()->unsigned();
            $table->string('user_email', 255)->nullable();
            $table->string('group', 50)->nullable();
            $table->string('role', 50)->nullable();
            $table->integer('role_id')->nullable()->unsigned();
            $table->bigInteger('object_id')->nullable()->unsigned();
            $table->string('object_name', 100)->nullable();
            $table->bigInteger('location_id')->nullable()->unsigned();
            $table->string('location_name', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->bigInteger('staff_id')->nullable()->unsigned();
            $table->text('metadata_user')->nullable();
            $table->text('metadata_object')->nullable();
            $table->text('metadata_location')->nullable();
            $table->text('metadata_staff')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 15)->default('active')->nullable();
            $table->string('response_status', 50)->nullable();
            $table->timestamps();

            $table->index(array('activity_id'), 'activityid_idx');
            $table->index(array('activity_name'), 'activity_name_idx');
            $table->index(array('activity_type'), 'activity_type_idx');
            $table->index(array('user_id'), 'userid_idx');
            $table->index(array('user_email'), 'user_email_idx');
            $table->index(array('group'), 'group_idx');
            $table->index(array('role'), 'role_idx');
            $table->index(array('role_id'), 'roleid_idx');
            $table->index(array('object_id'), 'objectid_idx');
            $table->index(array('location_id'), 'locationid_idx');
            $table->index(array('ip_address'), 'ip_address_idx');
            $table->index(array('user_agent'), 'user_agent_idx');
            $table->index(array('staff_id'), 'staffid_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('response_status'), 'response_status_idx');
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
        Schema::drop('activities');
    }
}
