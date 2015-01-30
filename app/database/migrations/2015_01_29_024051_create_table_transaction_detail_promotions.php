<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableTransactionDetailPromotions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_detail_promotions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('transaction_detail_promotion_id');
            $table->bigInteger('transaction_detail_id')->unsigned()->nullable();
            $table->bigInteger('transaction_id')->unsigned()->nullable();
            $table->bigInteger('promotion_id')->unsigned()->nullable();
            $table->string('promotion_name', 255)->nullable();
            $table->string('promotion_type', 15)->nullable();
            $table->string('rule_type', 50)->nullable();
            $table->decimal('rule_value', 16, 2)->nullable();
            $table->string('discount_object_type', 50)->nullable();
            $table->decimal('discount_value', 16, 2)->nullable();
            $table->decimal('value_after_percentage', 16, 2)->nullable();
            $table->string('description', 2000)->nullable();
            $table->datetime('begin_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('transaction_detail_promotions');
    }

}
