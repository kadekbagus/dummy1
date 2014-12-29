<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductVariantsAddStock extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_variants', function(Blueprint $table)
        {
            $table->integer('stock')->nullable()->unsigned()->after('sku');
            $table->index(array('stock'), 'stock_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_variants', function(Blueprint $table)
        {
            $table->dropIndex('stock_idx');
            $table->dropColumn('stock');
        });
    }
}
