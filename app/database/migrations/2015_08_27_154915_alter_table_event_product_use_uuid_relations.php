<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEventProductUseUuidRelations extends Migration {

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

        foreach($this->getColumnNames() as $args)
        {
            call_user_func_array([$this, 'alterTable'], $args);
        }
    }

    public function down()
    {
        # DO NOTHING
    }

    private function alterTable($tableName, $columnName, $isNullable)
    {
        $specialLength = ['session_id' => 40];
        $tableName     = DB::getTablePrefix() . $tableName;

        if (array_key_exists($columnName, $specialLength)) {
            $stmt = ("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` CHAR({$specialLength[$columnName]}) CHARACTER SET ASCII COLLATE ASCII_BIN;");
        } elseif ($isNullable == 'YES') {
            $stmt = ("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` CHAR(16) CHARACTER SET ASCII COLLATE ASCII_BIN;");
        } else {
            $stmt = ("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` CHAR(16) CHARACTER SET ASCII COLLATE ASCII_BIN NOT NULL;");
        }

        $ok = DB::statement($stmt);
        if (!$ok)
        {
            throw \Exception("FAIL: " . $stmt);
        }
    }

    private function getColumnNames() {
        return [
            ['event_product', 'product_id', 'NO'],
            ['event_product', 'event_id', 'NO'],
            ['event_product', 'event_product_id', 'NO']
        ];
    }
}
