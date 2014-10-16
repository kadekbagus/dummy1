<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIncrementsToPermissionRoleId extends Migration 
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'permission_role` MODIFY `permission_role_id` BIGINT UNSIGNED AUTO_INCREMENT');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'permission_role` MODIFY `allowed` VARCHAR(3) DEFAULT "yes" COMMENT "valid: yes, no"');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'permission_role` MODIFY `permission_role_id` BIGINT UNSIGNED');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'permission_role` MODIFY `allowed` VARCHAR(3)');
    }

}
