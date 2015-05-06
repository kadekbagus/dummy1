<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddColumnProvince extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function(Blueprint $table)
        {
            $table->string('province', 100)->nullable()->after('city')->default(NULL);
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
            $table->dropColumn('province');
        });
    }

}
