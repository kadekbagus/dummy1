<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductAttributesAddMerchantId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_attributes', function(Blueprint $table)
        {
            $table->integer('merchant_id')->nullable()->unsigned()->after('product_attribute_name');
            $table->index(array('merchant_id'), 'merchantid_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_attributes', function(Blueprint $table)
        {
            $table->dropIndex('merchantid_idx');
            $table->dropColumn('merchant_id');
        });
    }
}
