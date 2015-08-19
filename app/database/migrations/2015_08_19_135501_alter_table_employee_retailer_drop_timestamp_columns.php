<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEmployeeRetailerDropTimestampColumns extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table("employee_retailer", function (Blueprint $table) {
            $table->dropTimestamps();
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table("employee_retailer", function (Blueprint $table) {
            $table->timestamps();
        });
	}

}
