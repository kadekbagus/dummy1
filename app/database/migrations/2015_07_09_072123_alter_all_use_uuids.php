<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAllUseUuids extends Migration {

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
        $all_id_columns = DB::select(
                'SELECT table_name, column_name, is_nullable ' .
                'FROM information_schema.columns '.
                'WHERE table_schema = ? AND (column_name LIKE ? OR column_name = ?)', [DB::connection()->getDatabaseName(), '%_id', 'modified_by']);
        foreach ($all_id_columns as $col) {
            $stmt = '';
            if ($col->is_nullable == 'YES') {
                $stmt = ("ALTER TABLE `{$col->table_name}` MODIFY `{$col->column_name}` CHAR(22);");
            }
            else {
                $stmt = ("ALTER TABLE `{$col->table_name}` MODIFY `{$col->column_name}` CHAR(22) NOT NULL;");
            }
            $ok = DB::statement($stmt);
            if (!$ok) {
                throw \Exception("FAIL: " . $stmt);
            }
        }
	}

}
