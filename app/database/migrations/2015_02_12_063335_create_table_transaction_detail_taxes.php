<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableTransactionDetailTaxes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_detail_taxes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('transaction_detail_coupon_id');
            $table->bigInteger('transaction_detail_id')->unsigned()->nullable();
            $table->bigInteger('transaction_id')->unsigned()->nullable();
            $table->string('tax_name', 50)->nullable();
            $table->decimal('tax_value', 16, 2)->nullable();
            $table->integer('tax_order')->unsigned()->nullable();
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
        Schema::drop('transaction_detail_taxes');
    }

}
