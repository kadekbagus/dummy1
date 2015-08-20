<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablePromotionProductAddNewColumn extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('promotion_product', function (Blueprint $table) {
			$table->bigInteger('promotion_rule_id')->unsigned()->after('promotion_id');
            $table->string('object_type', 50)->nullable()->after('product_id');
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('promotion_product', function (Blueprint $table) {
			$table->dropColumn('promotion_rule_id');
            $table->dropColumn('object_type');
        });
	}

}
