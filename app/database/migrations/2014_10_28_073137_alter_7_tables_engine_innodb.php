<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Alter7TablesEngineInnodb extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = DB::getTablePrefix();

        // Currently we had 7 tables right know
        $tables = array(
            'apikeys',
            'custom_permission',
            'permission_role',
            'permissions',
            'roles',
            'user_details',
            'users'
        );

        // Change the engine to InnoDB
        foreach ($tables as $table) {
            DB::Statement("ALTER TABLE {$prefix}{$table} ENGINE=InnoDB");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // do nothing
    }

}
