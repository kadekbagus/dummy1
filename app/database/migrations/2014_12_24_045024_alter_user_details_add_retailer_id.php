<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUserDetailsAddRetailerId extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_details', function(Blueprint $table)
        {
            $table->integer('retailer_id')->unsigned()->nullable()->after('merchant_acquired_date');
            $table->index(array('retailer_id'), 'retailerid_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_details', function(Blueprint $table)
        {
            $table->dropIndex('retailerid_idx');
            $table->dropColumn('retailer_id');
        });
    }

}
