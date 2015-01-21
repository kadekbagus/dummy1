<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableWidgetsRenameWidgetsId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}widgets`
                       CHANGE COLUMN `widgets_id` `widget_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}widgets`
                       CHANGE COLUMN `widget_id` `widgets_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT");
    }
}
