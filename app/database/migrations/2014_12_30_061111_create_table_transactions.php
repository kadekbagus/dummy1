<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableTransactions extends Migration
{
    /**
	 * Run the migrations.
	 *
	 * @return void
	 */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('transaction_id');
            $table->string('transaction_code', 100)->nullable();
            $table->bigInteger('cashier_id')->unsigned()->nullable();
            $table->bigInteger('customer_id')->unsigned()->nullable();
            $table->bigInteger('merchant_id')->unsigned()->nullable();
            $table->bigInteger('retailer_id')->unsigned()->nullable();
            $table->integer('total_item')->unsigned()->nullable();
            $table->decimal('subtotal', 16, 2)->nullable();
            $table->decimal('vat', 16, 2)->nullable();
            $table->decimal('total_to_pay', 16, 2)->nullable();
            $table->string('payment_method', 50)->nullable();

            $table->index(array('transaction_id'), 'transactionid_idx');
            $table->index(array('transaction_code'), 'transactioncode_idx');
            $table->index(array('cashier_id'), 'cashierid_idx');
            $table->index(array('customer_id'), 'customerid_idx');
            $table->index(array('merchant_id'), 'merchantid_idx');
            $table->index(array('retailer_id'), 'retailerid_idx');
            $table->index(array('total_item'), 'totalitem_idx');
            $table->index(array('subtotal'), 'subtotal_idx');
            $table->index(array('vat'), 'vat_idx');
            $table->index(array('total_to_pay'), 'totaltopay_idx');
            $table->index(array('payment_method'), 'paymentmethod_idx');
        });
    }

    /**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
    public function down()
    {
        Schema::drop('transactions');
    }

}
