<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableIssuedCoupons extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('issued_coupons', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->increments('issued_coupon_id');
            $table->integer('promotion_id')->unsigned();
            $table->string('issued_coupon_code', 50);
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->datetime('expired_date')->nullable();
            $table->datetime('issued_date')->nullable();
            $table->datetime('redeemed_date')->nullable();
            $table->integer('issuer_retailer_id')->unsigned()->nullable();
            $table->string('status', 15);
            $table->timestamps();

            $table->index(array('promotion_id'), 'promotion_id_idx');
            $table->index(array('issued_coupon_code'), 'issued_coupon_code_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('user_id'), 'user_id_idx');
            $table->index(array('issuer_retailer_id'), 'issuer_retailer_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('issued_coupons');
    }

}
