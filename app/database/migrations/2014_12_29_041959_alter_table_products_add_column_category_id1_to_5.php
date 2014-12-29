<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductsAddColumnCategoryId1To5 extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function(Blueprint $table)
        {
            $table->integer('category_id1')->unsigned()->nullable()->after('merchant_id');
            $table->integer('category_id2')->unsigned()->nullable()->after('category_id1');
            $table->integer('category_id3')->unsigned()->nullable()->after('category_id2');
            $table->integer('category_id4')->unsigned()->nullable()->after('category_id3');
            $table->integer('category_id5')->unsigned()->nullable()->after('category_id4');
            $table->index(array('category_id1'), 'category_id1_idx');
            $table->index(array('category_id2'), 'category_id2_idx');
            $table->index(array('category_id3'), 'category_id3_idx');
            $table->index(array('category_id4'), 'category_id4_idx');
            $table->index(array('category_id5'), 'category_id5_idx');
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
            $table->dropIndex('category_id5_idx');
            $table->dropIndex('category_id4_idx');
            $table->dropIndex('category_id3_idx');
            $table->dropIndex('category_id2_idx');
            $table->dropIndex('category_id1_idx');
            $table->dropColumn('category_id5');
            $table->dropColumn('category_id4');
            $table->dropColumn('category_id3');
            $table->dropColumn('category_id2');
            $table->dropColumn('category_id1');
        });
    }

}
