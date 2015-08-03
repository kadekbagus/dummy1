<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DeleteColumnPromotionIdOnTablePromotionProduct extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('promotion_product', function (Blueprint $table) {
			$table->dropColumn('promotion_id');
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
			$table->bigInteger('promotion_id')->unsigned()->after('promotion_product_id');
        });
	}

}
