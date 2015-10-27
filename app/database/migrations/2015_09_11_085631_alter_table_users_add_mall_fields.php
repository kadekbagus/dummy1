<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableUsersAddMallFields extends Migration {

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
		$builder->table('users', function(OrbitBlueprint $table)
		{
			$table->string('membership_number', 50)->nullable()->after('remember_token');
            $table->datetime('membership_since')->nullable()->after('membership_number');
            $table->string('external_user_id', 50)->nullable()->after('membership_since');
            $table->index(array('membership_number'), 'membership_number_idx');
            $table->index(['external_user_id'], 'external_user_id_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('users', function(Blueprint $table)
		{
            $table->dropIndex('external_user_id_idx');
            $table->dropIndex('membership_number_idx');
            $table->dropColumn('external_user_id');
            $table->dropColumn('membership_since');
            $table->dropColumn('membership_number');
		});
	}

}
