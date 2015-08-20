<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductsAddDefaultValueIsAllRetailer extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		$prefix = DB::getTablePrefix();
		DB::statement("ALTER TABLE `{$prefix}products` MODIFY COLUMN `is_all_retailer` varchar(1) NULL DEFAULT 'N';");
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		$prefix = DB::getTablePrefix();
		DB::statement("ALTER TABLE `{$prefix}products` MODIFY COLUMN `is_all_retailer` varchar(1) NOT NULL DEFAULT 'N';");
	}

}
