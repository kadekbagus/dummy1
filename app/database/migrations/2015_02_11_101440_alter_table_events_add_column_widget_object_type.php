<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEventsAddColumnWidgetObjectType extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function(Blueprint $table)
        {
            $table->string('widget_object_type', 50)->nullable()->after('link_object_id5');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function(Blueprint $table)
        {
            $table->dropColumn('widget_object_type');
        });
    }

}
