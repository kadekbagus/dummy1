<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTransactionDetailtaxesTaxvalueDecimalpoint extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_detail_taxes', function(Blueprint $table)
        {
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'transaction_detail_taxes` MODIFY COLUMN `tax_value` DECIMAL(16,4)');
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'transaction_detail_taxes` MODIFY COLUMN `total_tax` DECIMAL(16,4)');
        }); 
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }

}
