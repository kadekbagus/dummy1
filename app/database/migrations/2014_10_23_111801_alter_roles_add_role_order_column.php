<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterRolesAddRoleOrderColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->integer('role_order')->unsigned()->default(0)->after('role_name');
            $table->index(array('role_order'), 'role_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex('role_order_idx');
            $table->dropColumn('role_order');
        });
    }

}
