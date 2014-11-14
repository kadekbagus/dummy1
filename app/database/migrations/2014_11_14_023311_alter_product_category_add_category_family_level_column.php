<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProductCategoryAddCategoryFamilyLevelColumn extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('product_category', function(Blueprint $table)
		{
			$table->integer('category_family_level')->unsigned()->nullable()->after('product_id');
			$table->index(array('category_family_level'), 'category_family_level_idx');
			$table->index(array('category_id', 'category_family_level'), 'categoryid_family_level_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::table('product_category', function (Blueprint $table) {
            $table->dropColumn('category_family_level');
            $table->dropIndex('category_family_level_idx');
            $table->dropIndex('categoryid_family_level_idx');
        });
	}

}
