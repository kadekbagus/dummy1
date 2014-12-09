<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantTaxesAddColumnStatus extends Migration 
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchant_taxes', function(Blueprint $table)
        {
            $table->string('status', 15)->nullable()->default('active')->after('tax_value');
            $table->index(array('status'), 'status_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_taxes', function(Blueprint $table)
        {
            $table->dropIndex('status_idx');
            $table->dropColumn('status');
        });
    }

}
