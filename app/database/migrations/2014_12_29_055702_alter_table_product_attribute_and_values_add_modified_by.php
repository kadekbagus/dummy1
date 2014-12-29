<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductAttributeAndValuesAddModifiedBy extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_attributes', function(Blueprint $table)
        {
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();

            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
        });

        Schema::table('product_attribute_values', function(Blueprint $table)
        {
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();

            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
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
            $table->dropIndex('created_by_idx');
            $table->dropIndex('modified_by_idx');

            $table->dropColumn('created_by');
            $table->dropColumn('modified_by');
        });

        Schema::table('product_attribute_values', function(Blueprint $table)
        {
            $table->dropIndex('created_by_idx');
            $table->dropIndex('modified_by_idx');

            $table->dropColumn('created_by');
            $table->dropColumn('modified_by');
        });
    }

}
