<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablePromotionRulesAddColumnIsAllProduct extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('promotion_rules', function (Blueprint $table) {
			$table->string('is_all_product_rule', 1)->nullable()->default('N')->after('rule_object_type');
            $table->string('is_all_product_discount', 1)->nullable()->default('N')->after('discount_object_type');
        });

        Schema::table('promotions', function (Blueprint $table) {
			$table->dropColumn('is_all_product');
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('promotion_rules', function (Blueprint $table) {
			$table->dropColumn('is_all_product_rule');
            $table->dropColumn('is_all_product_discount');
        });
	}

}
