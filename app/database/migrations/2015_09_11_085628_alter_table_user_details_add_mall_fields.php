<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableUserDetailsAddMallFields extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		$builder = DB::connection()->getSchemaBuilder();
		$builder->blueprintResolver(function ($table, $callback) {
			return new OrbitBlueprint($table, $callback);
		});

		$builder->table('user_details', function(OrbitBlueprint $table)
		{
			$table->datetime('date_of_work')->nullable()->default(NULL)->after('occupation');
			$table->string('phone3', 50)->nullable()->default(null)->after('phone2');
            $table->string('idcard', '30')->nullable()->default(NULL)->after('relationship_status');

            $table->index(['date_of_work'], 'date_of_work_idx');
            $table->index(['phone3'], 'phone3_idx');
            $table->index(['idcard'], 'idcard_idx');
		});

        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` MODIFY `relationship_status` VARCHAR(30) NULL DEFAULT NULL');
         DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` MODIFY `postal_code` VARCHAR(50) NULL DEFAULT NULL');
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` MODIFY `postal_code` INT(10) UNSIGNED NULL DEFAULT NULL');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` MODIFY `relationship_status` VARCHAR(10) NULL DEFAULT NULL');

		Schema::table('user_details', function(Blueprint $table)
		{
			$table->dropIndex('idcard_idx');
            $table->dropIndex('phone3_idx');
            $table->dropIndex('date_of_work_idx');

            $table->dropColumn('idcard');
            $table->dropColumn('phone3');
            $table->dropColumn('date_of_work');
		});
	}

}
