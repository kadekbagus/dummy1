<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableSettingsFixSettingMissingT extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}settings`
                       CHANGE COLUMN `seting_value` `setting_value` TEXT CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}settings`
                       CHANGE COLUMN `setting_value` `seting_value` TEXT CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL");
    }
}
