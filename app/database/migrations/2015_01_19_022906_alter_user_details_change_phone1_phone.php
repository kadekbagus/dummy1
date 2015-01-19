<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUserDetailsChangePhone1Phone extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}user_details`
                       CHANGE COLUMN `phone1` `phone` VARCHAR(50) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}user_details`
                       CHANGE COLUMN `phone` `phone1` VARCHAR(50) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL");
    }
}
