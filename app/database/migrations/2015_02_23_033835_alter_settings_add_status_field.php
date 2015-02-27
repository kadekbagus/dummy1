<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterSettingsAddStatusField extends Migration
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
                       CHANGE COLUMN `object_id` `object_id` BIGINT(20) UNSIGNED NULL DEFAULT '0'");

        Schema::table('settings', function(Blueprint $table)
        {
            $table->bigInteger('modified_by')->nullable()->unsigned()->after('object_type');
            $table->string('status', 15)->nullable()->default('active')->after('modified_by');

            $table->index(array('status'), 'status_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('setting_name', 'object_id', 'object_type', 'status'), 'objectid_settingname_idx');
        });
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
                       CHANGE COLUMN `object_id` `object_id` BIGINT(20) NULL DEFAULT '0'");

        Schema::table('settings', function(Blueprint $table)
        {
            $table->dropIndex('status_idx');
            $table->dropIndex('modified_by_idx');
            $table->dropIndex('objectid_settingname_idx');
            $table->dropColumn('status');
            $table->dropColumn('modified_by');
        });
    }
}
