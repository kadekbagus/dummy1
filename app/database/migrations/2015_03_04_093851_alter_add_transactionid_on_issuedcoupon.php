<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAddTransactionidOnIssuedcoupon extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('issued_coupons', function(Blueprint $table)
        {
            $table->bigInteger('transaction_id')->unsigned()->nullable()->after('promotion_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('issued_coupons', function(Blueprint $table)
        {
            $table->dropColumn('transaction_id');
        });
    }

}
