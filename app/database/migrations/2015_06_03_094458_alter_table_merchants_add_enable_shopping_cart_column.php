<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddEnableShoppingCartColumn extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function(Blueprint $table)
        {
            $table->string('enable_shopping_cart', 3)->nullable()->after('modified_by')->default('yes');
            $table->index(array('enable_shopping_cart'), 'enable_shopping_cart_idx');
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
            $table->dropIndex('enable_shopping_cart_idx');
            $table->dropColumn('enable_shopping_cart');
        });
    }

}
