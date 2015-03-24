<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablePosQuickProductsAddRetailerId extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pos_quick_products', function(Blueprint $table)
        {
            $table->bigInteger('retailer_id')->unsigned()->nullable()->after('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pos_quick_products', function(Blueprint $table)
        {
            $table->dropColumn('retailer_id');
        });
    }

}
