<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableActivitiesAddIndexColumn extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities', function(Blueprint $table)
        {
            $table->index(array('object_name'), 'object_name_idx');
            $table->index(array('activity_name_long'), 'activity_name_long_idx');
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
            $table->dropIndex('object_name_idx');
            $table->dropIndex('activity_name_long_idx');
        });
    }

}
