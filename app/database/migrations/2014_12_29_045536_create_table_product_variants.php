<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableProductVariants extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_variants', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('product_variant_id');
            $table->bigInteger('product_id')->unsigned()->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->string('upc', 30)->nullable();
            $table->string('sku', 30)->nullable();
            $table->integer('product_attribute_value_id1')->unsigned()->nullable();
            $table->integer('product_attribute_value_id2')->unsigned()->nullable();
            $table->integer('product_attribute_value_id3')->unsigned()->nullable();
            $table->integer('product_attribute_value_id4')->unsigned()->nullable();
            $table->integer('product_attribute_value_id5')->unsigned()->nullable();
            $table->bigInteger('merchant_id')->unsigned()->nullable();
            $table->bigInteger('retailer_id')->unsigned()->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();
            $table->timestamps();

            $table->index(array('product_id'), 'productid_idx');
            $table->index(array('price'), 'price_idx');
            $table->index(array('upc'), 'upc_idx');
            $table->index(array('sku'), 'sku_idx');
            $table->index(array('product_attribute_value_id1'), 'product_attribute_value_id1_idx');
            $table->index(array('product_attribute_value_id2'), 'product_attribute_value_id2_idx');
            $table->index(array('product_attribute_value_id3'), 'product_attribute_value_id3_idx');
            $table->index(array('product_attribute_value_id4'), 'product_attribute_value_id4_idx');
            $table->index(array('product_attribute_value_id5'), 'product_attribute_value_id5_idx');
            $table->index(array('merchant_id'), 'merchantid_idx');
            $table->index(array('retailer_id'), 'retailerid_idx');
            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('product_variant_id', 'product_id'), 'product_variant_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('product_variants');
    }

}
