<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableCarts extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('carts', function(Blueprint $table)
		{
			$table->engine = 'InnoDB';
            $table->bigIncrements('cart_id');
            $table->string('cart_code', 100)->nullable();
            $table->bigInteger('customer_id')->unsigned()->nullable();
            $table->bigInteger('merchant_id')->unsigned()->nullable();
            $table->bigInteger('retailer_id')->unsigned()->nullable();
            $table->integer('total_item')->unsigned()->nullable();
            $table->decimal('subtotal', 16, 2)->nullable();
            $table->decimal('vat', 16, 2)->nullable();
            $table->decimal('total_to_pay', 16, 2)->nullable();
            $table->string('status', 15);
            $table->timestamps();

            $table->index(array('cart_id'), 'cartid_idx');
            $table->index(array('cart_code'), 'cartcode_idx');
            $table->index(array('customer_id'), 'customerid_idx');
            $table->index(array('merchant_id'), 'merchantid_idx');
            $table->index(array('retailer_id'), 'retailerid_idx');
            $table->index(array('total_item'), 'totalitem_idx');
            $table->index(array('subtotal'), 'subtotal_idx');
            $table->index(array('vat'), 'vat_idx');
            $table->index(array('total_to_pay'), 'totaltopay_idx');
            $table->index(array('status'), 'status_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('carts');
	}

}
