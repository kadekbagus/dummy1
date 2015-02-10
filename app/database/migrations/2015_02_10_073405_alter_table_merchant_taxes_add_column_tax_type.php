<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantTaxesAddColumnTaxType extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchant_taxes', function(Blueprint $table)
        {
            $table->string('tax_type', 15)->nullable()->after('tax_name');
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
            $table->dropColumn('tax_type');
        });
    }

}
