<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableCategories extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories', function(Blueprint $table)
        {
            $table->dropIndex('category_name_idx');
            $table->dropIndex('parentid_idx');
            $table->dropIndex('category_parentid_idx');
            $table->dropIndex('parentid_order_status_idx');
            $table->dropColumn('parent_id');
            $table->integer('merchant_id')->unsigned()->after('category_id');
            $table->integer('category_level')->unsigned()->nullable()->after('category_name');
            $table->bigInteger('created_by')->unsigned()->nullable()->after('status');
            $table->unique(array('category_name'), 'category_name_unique');
            $table->index(array('merchant_id'), 'merchant_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories', function(Blueprint $table)
        {
            $table->dropIndex('merchant_id_idx');
            $table->dropUnique('category_name_unique');
            $table->dropColumn('created_by');
            $table->dropColumn('category_level');
            $table->dropColumn('merchant_id');
            $table->integer('parent_id')->unsigned()->after('category_name');
            $table->index(array('parent_id', 'category_order', 'status'), 'parentid_order_status_idx');
            $table->index(array('category_name', 'parent_id'), 'category_parentid_idx');
            $table->index(array('parent_id'), 'parentid_idx');
            $table->index(array('category_name'), 'category_name_idx');
        });
    }

}
