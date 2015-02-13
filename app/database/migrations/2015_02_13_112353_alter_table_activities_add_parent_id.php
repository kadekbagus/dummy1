<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableActivitiesAddParentId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities', function(Blueprint $table)
        {
            $table->bigInteger('parent_id')->nullable()->default(NULL)->after('status');
            $table->index(array('parent_id'), 'parentid_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activities', function(Blueprint $table)
        {
            $table->dropIndex('parentid_idx');
            $table->dropColumn('parent_id');
        });
    }
}
