<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablePromotionModifyColumnIsAllRetailer extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		$prefix = DB::getTablePrefix();
		DB::statement("ALTER TABLE `{$prefix}promotions` MODIFY COLUMN `is_all_retailer` varchar(1) NULL DEFAULT 'N';");
		DB::statement("ALTER TABLE `{$prefix}promotions` MODIFY COLUMN `location_id` BIGINT(20) NULL;");
		DB::statement("ALTER TABLE `{$prefix}promotions` MODIFY COLUMN `location_type` varchar(32) NULL;");
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		$prefix = DB::getTablePrefix();
		DB::statement("ALTER TABLE `{$prefix}promotions` MODIFY COLUMN `is_all_retailer` varchar(1) NOT NULL DEFAULT 'N';");
		DB::statement("ALTER TABLE `{$prefix}promotions` MODIFY COLUMN `location_id` BIGINT(20) NOT NULL;");
		DB::statement("ALTER TABLE `{$prefix}promotions` MODIFY COLUMN `location_type` varchar(32) NOT NULL;");
	}

}
