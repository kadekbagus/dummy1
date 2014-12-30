<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableTransactionDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('transaction_detail_id');
            $table->bigInteger('transaction_id')->unsigned()->nullable();
            $table->bigInteger('product_id')->unsigned()->nullable();
            $table->decimal('price', 16, 2)->nullable();
            $table->string('product_code', 20)->nullable();
            $table->string('upc', 30)->nullable();
            $table->string('sku', 30)->nullable();
            $table->integer('quantity')->unsigned()->nullable();
            $table->bigInteger('product_variant_id')->unsigned()->nullable();
            $table->timestamps();

            $table->index(array('transaction_detail_id'), 'transaction_detailid_idx');
            $table->index(array('transaction_id'), 'transactionid_idx');
            $table->index(array('price'), 'price_idx');
            $table->index(array('product_code'), 'productcode_idx');
            $table->index(array('upc'), 'upc_idx');
            $table->index(array('sku'), 'sku_idx');
            $table->index(array('quantity'), 'quantity_idx');
            $table->index(array('product_variant_id'), 'product_variantid_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('transaction_details');
    }

}
