<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIncrementsToCustomPermissionId extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'custom_permission` MODIFY `custom_permission_id` BIGINT UNSIGNED AUTO_INCREMENT');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'custom_permission` MODIFY `allowed` VARCHAR(3) DEFAULT "no" COMMENT "valid: yes, no"');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'custom_permission` MODIFY `custom_permission_id` BIGINT UNSIGNED');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'custom_permission` MODIFY `allowed` VARCHAR(3)');
    }

}
