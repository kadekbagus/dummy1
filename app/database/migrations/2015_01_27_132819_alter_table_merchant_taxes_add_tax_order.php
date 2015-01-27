<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantTaxesAddTaxOrder extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchant_taxes', function(Blueprint $table)
        {
            $table->tinyInteger('tax_order')->unsigned()->default(0)->after('tax_value');
            $table->index(array('merchant_id', 'tax_order'), 'merchantid_taxorder_idx');
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
            $table->dropIndex('merchantid_taxorder_idx');
            $table->dropColumn('tax_order');
        });
    }

}
