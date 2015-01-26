<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTablePosQuickProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_quick_products', function(Blueprint $table)
        {
            $table->bigincrements('pos_quick_product_id');
            $table->bigInteger('product_id')->unsingned();
            $table->bigInteger('merchant_id')->unsingned();
            $table->bigInteger('product_order')->unsingned();
            $table->timestamps();

            $table->index(array('product_id'), 'productid_idx');
            $table->index(array('merchant_id'), 'merchantid_idx');
            $table->index(array('product_order'), 'product_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('pos_quick_products');
    }

}
