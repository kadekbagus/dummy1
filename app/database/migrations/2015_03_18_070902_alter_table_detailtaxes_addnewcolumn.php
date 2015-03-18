<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableDetailtaxesAddnewcolumn extends Migration 
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_detail_taxes', function(Blueprint $table)
        {
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'transaction_detail_taxes` CHANGE `transaction_detail_coupon_id` `transaction_detail_tax_id` BIGINT(20)');
            $table->decimal('total_tax', 16, 2)->nullable()->after('tax_value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_detail_taxes', function(Blueprint $table)
        {
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'transaction_detail_taxes` CHANGE `transaction_detail_tax_id` `transaction_detail_coupon_id` BIGINT(20)');
            $table->dropColumn('total_tax');
        });
    }

}
