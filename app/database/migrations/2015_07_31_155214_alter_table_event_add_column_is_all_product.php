<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEventAddColumnIsAllProduct extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('events', function (Blueprint $table) {
			$table->string('is_all_product', 1)->nullable()->default('N')->after('is_all_retailer');
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('events', function (Blueprint $table) {
			$table->dropColumn('is_all_product');
        });
	}

}
