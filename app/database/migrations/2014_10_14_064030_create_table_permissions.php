<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTablePermissions extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('permissions', function(Blueprint $table)
        {
            $table->integer('permission_id')->unsigned();
            $table->string('permission_name', 50);
            $table->string('permission_label', 50);
            $table->string('permission_group', 50);
            $table->string('permission_group_label', 50);
            $table->integer('permission_name_order')->unsigned();
            $table->integer('permission_group_order')->unsigned();
            $table->bigInteger('modified_by')->unsigned();
            $table->timestamps();
            $table->primary(array('permission_id'));
            $table->index(array('permission_name_order'), 'permission_name_order_idx');
            $table->index(array('permission_group_order'), 'permission_group_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('permissions');
    }

}
