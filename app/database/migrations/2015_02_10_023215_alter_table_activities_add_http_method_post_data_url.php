<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableActivitiesAddHttpMethodPostDataUrl extends Migration
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
            $table->string('http_method', 8)->nullable()->default(NULL)->after('notes');
            $table->string('request_uri', 4912)->nullable()->default(NULL)->after('http_method');
            $table->text('post_data')->nullable()->default(NULL)->after('request_uri');

            $table->index(array('http_method'), 'http_method_idx');
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
            $table->dropIndex('http_method_idx');
            $table->dropColumn('http_method');
            $table->dropColumn('request_uri');
            $table->dropColumn('post_data');
        });
    }
}
