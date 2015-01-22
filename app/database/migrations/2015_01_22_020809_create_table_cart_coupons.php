<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableCartCoupons extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('cart_coupons', function(Blueprint $table)
		{
			$table->engine = 'InnoDB';
			$table->bigIncrements('cart_coupon_id');
			$table->bigInteger('issued_coupon_id');
			$table->varchar('object_type', 50);
			$table->bigInteger('object_id');
			$table->timestamps();

			$table->index(array('cart_coupon_id'), 'cart_couponid_idx');
			$table->index(array('issued_coupon_id'), 'issued_couponid_idx');
			$table->index(array('object_type'), 'object_type_idx');
			$table->index(array('object_id'), 'objectid_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('cart_coupons');
	}

}
