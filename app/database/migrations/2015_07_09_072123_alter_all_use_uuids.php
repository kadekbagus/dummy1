<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAllUseUuids extends Migration {

    /**
     * Run the migrations.
     *
     * @throws
     */
	public function up()
	{
        $builder = DB::connection()->getSchemaBuilder();
        $builder->blueprintResolver(function ($table, $callback) {
            return new OrbitBlueprint($table, $callback);
        });

        $specialLength = ['session_id' => 40];

        $all_id_columns = DB::select(
                'SELECT table_name, column_name, is_nullable ' .
                'FROM information_schema.columns '.
                'WHERE table_schema = ? AND (column_name LIKE ? ESCAPE \'!\' OR column_name LIKE ?  ESCAPE \'!\' OR column_name = ?)', [DB::connection()->getDatabaseName(), '%!_id', '%!_id_', 'modified_by']);
        foreach ($all_id_columns as $col) {
            $stmt = '';
            if (array_key_exists($col->column_name, $specialLength)) {
                $stmt = ("ALTER TABLE `{$col->table_name}` MODIFY `{$col->column_name}` CHAR({$specialLength[$col->column_name]}) CHARACTER SET ASCII COLLATE ASCII_BIN;");
            } elseif ($col->is_nullable == 'YES') {
                $stmt = ("ALTER TABLE `{$col->table_name}` MODIFY `{$col->column_name}` CHAR(16) CHARACTER SET ASCII COLLATE ASCII_BIN;");
            } else {
                $stmt = ("ALTER TABLE `{$col->table_name}` MODIFY `{$col->column_name}` CHAR(16) CHARACTER SET ASCII COLLATE ASCII_BIN NOT NULL;");
            }
            $ok = DB::statement($stmt);
            if (!$ok) {
                throw \Exception("FAIL: " . $stmt);
            }
        }
	}

    public function down()
    {
        # DO NOTHING
    }

}
