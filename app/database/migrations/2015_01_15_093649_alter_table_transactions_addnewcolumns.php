<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableTransactionsAddnewcolumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_details', function(Blueprint $table)
        {
            $table->decimal('variant_price', 14, 2)->nullable()->after('product_variant_id');
            $table->string('variant_upc', 30)->nullable()->after('variant_price');
            $table->string('variant_sku', 30)->nullable()->after('variant_upc');
            $table->integer('variant_stock')->unsigned()->nullable()->after('variant_sku');
            $table->integer('product_attribute_value_id1')->unsigned()->nullable()->after('variant_stock');
            $table->integer('product_attribute_value_id2')->unsigned()->nullable()->after('product_attribute_value_id1');
            $table->integer('product_attribute_value_id3')->unsigned()->nullable()->after('product_attribute_value_id2');
            $table->integer('product_attribute_value_id4')->unsigned()->nullable()->after('product_attribute_value_id3');
            $table->integer('product_attribute_value_id5')->unsigned()->nullable()->after('product_attribute_value_id4');
            $table->string('product_attribute_value1', 100)->nullable()->after('product_attribute_value_id5');
            $table->string('product_attribute_value2', 100)->nullable()->after('product_attribute_value1');
            $table->string('product_attribute_value3', 100)->nullable()->after('product_attribute_value2');
            $table->string('product_attribute_value4', 100)->nullable()->after('product_attribute_value3');
            $table->string('product_attribute_value5', 100)->nullable()->after('product_attribute_value4');
            $table->integer('merchant_tax_id1')->unsigned()->nullable()->after('product_attribute_value5');
            $table->integer('merchant_tax_id2')->unsigned()->nullable()->after('merchant_tax_id1');
            $table->integer('attribute_id1')->unsigned()->nullable()->after('merchant_tax_id2');
            $table->integer('attribute_id2')->unsigned()->nullable()->after('attribute_id1');
            $table->integer('attribute_id3')->unsigned()->nullable()->after('attribute_id2');
            $table->integer('attribute_id4')->unsigned()->nullable()->after('attribute_id3');
            $table->integer('attribute_id5')->unsigned()->nullable()->after('attribute_id4');
            $table->string('product_attribute_name1', 50)->nullable()->after('attribute_id5');
            $table->string('product_attribute_name2', 50)->nullable()->after('product_attribute_name1');
            $table->string('product_attribute_name3', 50)->nullable()->after('product_attribute_name2');
            $table->string('product_attribute_name4', 50)->nullable()->after('product_attribute_name3');
            $table->string('product_attribute_name5', 50)->nullable()->after('product_attribute_name4');

            $table->index(array('variant_price'), 'variant_price_idx');
            $table->index(array('variant_upc'), 'variant_upc_idx');
            $table->index(array('variant_sku'), 'variant_sku_idx');
            $table->index(array('variant_stock'), 'variant_stock_idx');
            $table->index(array('product_attribute_value_id1'), 'product_attribute_value_id1_idx');
            $table->index(array('product_attribute_value_id2'), 'product_attribute_value_id2_idx');
            $table->index(array('product_attribute_value_id3'), 'product_attribute_value_id3_idx');
            $table->index(array('product_attribute_value_id4'), 'product_attribute_value_id4_idx');
            $table->index(array('product_attribute_value_id5'), 'product_attribute_value_id5_idx');
            $table->index(array('product_attribute_value1'), 'product_attribute_value1_idx');
            $table->index(array('product_attribute_value2'), 'product_attribute_value2_idx');
            $table->index(array('product_attribute_value3'), 'product_attribute_value3_idx');
            $table->index(array('product_attribute_value4'), 'product_attribute_value4_idx');
            $table->index(array('product_attribute_value5'), 'product_attribute_value5_idx');
            $table->index(array('merchant_tax_id1'), 'merchant_tax_id1_idx');
            $table->index(array('merchant_tax_id2'), 'merchant_tax_id2_idx');
            $table->index(array('attribute_id1'), 'attribute_id1_idx');
            $table->index(array('attribute_id2'), 'attribute_id2_idx');
            $table->index(array('attribute_id3'), 'attribute_id3_idx');
            $table->index(array('attribute_id4'), 'attribute_id4_idx');
            $table->index(array('attribute_id5'), 'attribute_id5_idx');
            $table->index(array('product_attribute_name1'), 'product_attribute_name1_idx');
            $table->index(array('product_attribute_name2'), 'product_attribute_name2_idx');
            $table->index(array('product_attribute_name3'), 'product_attribute_name3_idx');
            $table->index(array('product_attribute_name4'), 'product_attribute_name4_idx');
            $table->index(array('product_attribute_name5'), 'product_attribute_name5_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_details', function(Blueprint $table)
        {
            $table->dropIndex('variant_price_idx');
            $table->dropIndex('variant_upc_idx');
            $table->dropIndex('variant_sku_idx');
            $table->dropIndex('variant_stock_idx');
            $table->dropIndex('product_attribute_value_id1_idx');
            $table->dropIndex('product_attribute_value_id2_idx');
            $table->dropIndex('product_attribute_value_id3_idx');
            $table->dropIndex('product_attribute_value_id4_idx');
            $table->dropIndex('product_attribute_value_id5_idx');
            $table->dropIndex('product_attribute_value1_idx');
            $table->dropIndex('product_attribute_value2_idx');
            $table->dropIndex('product_attribute_value3_idx');
            $table->dropIndex('product_attribute_value4_idx');
            $table->dropIndex('product_attribute_value5_idx');
            $table->dropIndex('merchant_tax_id1_idx');
            $table->dropIndex('merchant_tax_id2_idx');
            $table->dropIndex('attribute_id1_idx');
            $table->dropIndex('attribute_id2_idx');
            $table->dropIndex('attribute_id3_idx');
            $table->dropIndex('attribute_id4_idx');
            $table->dropIndex('attribute_id5_idx');
            $table->dropIndex('product_attribute_name1_idx');
            $table->dropIndex('product_attribute_name2_idx');
            $table->dropIndex('product_attribute_name3_idx');
            $table->dropIndex('product_attribute_name4_idx');
            $table->dropIndex('product_attribute_name5_idx');

            $table->dropColumn('variant_price');
            $table->dropColumn('variant_upc');
            $table->dropColumn('variant_sku');
            $table->dropColumn('variant_stock');
            $table->dropColumn('product_attribute_value_id1');
            $table->dropColumn('product_attribute_value_id2');
            $table->dropColumn('product_attribute_value_id3');
            $table->dropColumn('product_attribute_value_id4');
            $table->dropColumn('product_attribute_value_id5');
            $table->dropColumn('product_attribute_value1');
            $table->dropColumn('product_attribute_value2');
            $table->dropColumn('product_attribute_value3');
            $table->dropColumn('product_attribute_value4');
            $table->dropColumn('product_attribute_value5');
            $table->dropColumn('merchant_tax_id1');
            $table->dropColumn('merchant_tax_id2');
            $table->dropColumn('attribute_id1');
            $table->dropColumn('attribute_id2');
            $table->dropColumn('attribute_id3');
            $table->dropColumn('attribute_id4');
            $table->dropColumn('attribute_id5');
            $table->dropColumn('product_attribute_name1');
            $table->dropColumn('product_attribute_name2');
            $table->dropColumn('product_attribute_name3');
            $table->dropColumn('product_attribute_name4');
            $table->dropColumn('product_attribute_name5');
        });
    }

}
