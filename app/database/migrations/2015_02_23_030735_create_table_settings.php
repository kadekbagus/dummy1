<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settings', function(Blueprint $table)
        {
            $table->bigIncrements('setting_id');
            $table->string('setting_name', 100);
            $table->text('seting_value');
            $table->bigInteger('object_id')->nullable()->default(0);
            $table->string('object_type')->nullable()->default(NULL);
            $table->timestamps();

            $table->index(array('setting_name'), 'setting_name_idx');
            $table->index(array('object_id'), 'objectid_idx');
            $table->index(array('object_type'), 'object_type_idx');
            $table->index(array('setting_name', 'object_id', 'object_type'), 'objectid_type_settingname_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('settings');
    }

}
