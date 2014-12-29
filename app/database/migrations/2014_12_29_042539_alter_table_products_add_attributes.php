<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductsAddAttributes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function(Blueprint $table)
        {
            $table->integer('attribute_id1')->unsigned()->nullable()->after('merchant_id');
            $table->integer('attribute_id2')->unsigned()->nullable()->after('attribute_id1');
            $table->integer('attribute_id3')->unsigned()->nullable()->after('attribute_id2');
            $table->integer('attribute_id4')->unsigned()->nullable()->after('attribute_id3');
            $table->integer('attribute_id5')->unsigned()->nullable()->after('attribute_id4');

            $table->index(array('attribute_id1'), 'attribute_id1_idx');
            $table->index(array('attribute_id2'), 'attribute_id2_idx');
            $table->index(array('attribute_id3'), 'attribute_id3_idx');
            $table->index(array('attribute_id4'), 'attribute_id4_idx');
            $table->index(array('attribute_id5'), 'attribute_id5_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function(Blueprint $table)
        {
            $table->dropIndex('attribute_id1_idx');
            $table->dropIndex('attribute_id2_idx');
            $table->dropIndex('attribute_id3_idx');
            $table->dropIndex('attribute_id4_idx');
            $table->dropIndex('attribute_id5_idx');

            $table->dropColumn('attribute_id1');
            $table->dropColumn('attribute_id2');
            $table->dropColumn('attribute_id3');
            $table->dropColumn('attribute_id4');
            $table->dropColumn('attribute_id5');
        });
    }
}
