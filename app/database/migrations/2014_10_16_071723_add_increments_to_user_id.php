<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIncrementsToUserId extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `user_id` BIGINT UNSIGNED AUTO_INCREMENT');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `status` VARCHAR(20) DEFAULT "pending" COMMENT "valid: active, pending, blocked, or deleted"');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` DROP COLUMN `user_status`');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `user_id` BIGINT UNSIGNED');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `status` VARCHAR(20)');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` ADD `user_status` VARCHAR(20)');
    }

}
