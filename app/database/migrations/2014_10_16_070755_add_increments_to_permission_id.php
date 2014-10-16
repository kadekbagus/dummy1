<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIncrementsToPermissionId extends Migration 
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'permissions` MODIFY `permission_id` INT UNSIGNED AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'permissions` MODIFY `permission_id` INT UNSIGNED');
    }

}
