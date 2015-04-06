<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableTransactionAddcolumnCurrency extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function(Blueprint $table)
        {
            $table->char('currency', 3)->nullable()->default('USD')->after('vat');
            $table->char('currency_symbol', 3)->nullable()->default('$')->after('currency');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function(Blueprint $table)
        {
            $table->dropColumn('currency');
            $table->dropColumn('currency_symbol');
        });
    }

}
