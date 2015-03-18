<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableDetailtaxesAddnewcolumnAgain extends Migration 
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
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'transaction_detail_taxes` MODIFY COLUMN `transaction_detail_tax_id` BIGINT(20) NOT NULL');
            $table->integer('tax_id')->unsigned()->nullable()->after('transaction_id');
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
            $table->dropColumn('tax_id');
        });
    }

}
