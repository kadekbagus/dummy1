<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTablePromotions extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotions', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->increments('promotion_id');
            $table->integer('merchant_id')->unsigned();
            $table->string('promotion_name', 255);
            $table->string('promotion_type', 15);
            $table->string('description', 2000)->nullable();
            $table->datetime('begin_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->char('is_permanent', 1)->nullable()->default('N');
            $table->string('status', 15);
            $table->string('image', 255)->nullable();
            $table->char('is_coupon', 1)->nullable()->default('N');
            $table->integer('maximum_issued_coupon');
            $table->integer('coupon_validity_in_days');
            $table->char('coupon_notification', 1)->nullable()->default('N');
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();
            $table->timestamps();

            $table->index(array('merchant_id'), 'merchant_id_idx');
            $table->index(array('promotion_name'), 'promotion_name_idx');
            $table->index(array('promotion_type'), 'promotion_type_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('begin_date', 'end_date'), 'begindate_enddate_idx');
            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('created_at'), 'created_at_idx');
            $table->index(array('is_permanent'), 'is_permanent_idx');
            $table->index(array('is_coupon'), 'is_coupon_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('promotions');
    }

}
