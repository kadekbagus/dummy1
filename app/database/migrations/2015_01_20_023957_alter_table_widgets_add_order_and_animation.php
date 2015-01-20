<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableWidgetsAddOrderAndAnimation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('widgets', function(Blueprint $table)
        {
            $table->tinyInteger('widget_order')->nullable()->unsigned()->default(0)->after('widget_slogan');
            $table->string('animation', 30)->nullable()->default('none')->after('merchant_id');

            $table->index(array('widget_order'), 'widget_order_idx');
            $table->index(array('animation'), 'animation_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('widgets', function(Blueprint $table)
        {
            $table->dropIndex('animation_idx');
            $table->dropIndex('widget_order_idx');

            $table->dropColumn('widget_order');
            $table->dropColumn('animation');
        });
    }
}
