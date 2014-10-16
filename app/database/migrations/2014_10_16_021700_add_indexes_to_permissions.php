<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToPermissions extends Migration 
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('permissions', function(Blueprint $table)
        {
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
        Schema::table('permissions', function(Blueprint $table)
        {
            $table->dropIndex('permission_name_order_idx');
            $table->dropIndex('permission_group_order_idx');
        });
    }

}
