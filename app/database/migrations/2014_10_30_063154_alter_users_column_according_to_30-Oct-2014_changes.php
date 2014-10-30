<?php

use Illuminate\Database\Migrations\Migration;

class AlterUsersColumnAccordingTo30Oct2014Changes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `user_firstname` VARCHAR(50) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `user_lastname` VARCHAR(75) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `user_last_login` DATETIME NULL DEFAULT NULL');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `user_ip` VARCHAR(45) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `modified_by` BIGINT NOT NULL DEFAULT 0');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `user_firstname` VARCHAR(50)');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `user_lastname` VARCHAR(75)');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `user_last_login` DATETIME');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `user_ip` VARCHAR(45)');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'users` MODIFY `modified_by` BIGINT');
    }

}
