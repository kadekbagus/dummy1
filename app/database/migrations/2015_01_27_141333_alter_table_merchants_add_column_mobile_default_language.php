<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddColumnMobileDefaultLanguage extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function(Blueprint $table)
        {
            $table->string('mobile_default_language', 15)->nullable()->after('slavebox_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function(Blueprint $table)
        {
            $table->dropColumn('mobile_default_language');
        });
    }

}
