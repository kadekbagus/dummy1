<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddNewColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dateTime('end_date_activity')->nullable()->after('start_date_activity');
            $table->string('url', 255)->nullable()->after('parent_id');
            $table->string('masterbox_number', 20)->nullable()->after('url');
            $table->string('slavebox_number', 20)->nullable()->after('masterbox_number');

            $table->index(array('end_date_activity'), 'merchant_end_date_activity_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropIndex('merchant_end_date_activity_idx');

            $table->dropColumn('end_date_activity');
            $table->dropColumn('url');
            $table->dropColumn('masterbox_number');
            $table->dropColumn('slavebox_number');
        });
    }

}
