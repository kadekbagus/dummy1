<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductVariantAddDefaultVariant extends Migration
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
            $table->char('default_variant', 3)->nullable()->default('no')->after('retailer_id');
            $table->index(array('default_variant'), 'default_variant_idx');
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
            $table->dropIndex('default_variant_idx');
            $table->dropColumn('default_variant');
        });
    }

}
