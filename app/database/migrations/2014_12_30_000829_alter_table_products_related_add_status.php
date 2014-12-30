<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductsRelatedAddStatus extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_attributes', function(Blueprint $table)
        {
            $table->string('status', 15)->default('active')->after('merchant_id');
            $table->index(array('status'), 'status_idx');
        });

        Schema::table('product_attribute_values', function(Blueprint $table)
        {
            $table->string('status', 15)->default('active')->after('value');
            $table->index(array('status'), 'status_idx');
        });

        Schema::table('product_variants', function(Blueprint $table)
        {
            $table->string('status', 15)->default('active')->after('merchant_id');
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
        Schema::table('product_attributes', function(Blueprint $table)
        {
            $table->dropIndex('status_idx');
            $table->dropColumn('status');
        });

        Schema::table('product_attribute_values', function(Blueprint $table)
        {
            $table->dropIndex('status_idx');
            $table->dropColumn('status');
        });

        Schema::table('product_variants', function(Blueprint $table)
        {
            $table->dropIndex('status_idx');
            $table->dropColumn('status');
        });
    }

}
