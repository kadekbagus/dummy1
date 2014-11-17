<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEngineInnodb extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		$prefix = DB::getTablePrefix();

        $tables = array(
            'merchants',
            'products',
            'categories',
            'product_category'
        );

        // Change the engine to InnoDB
        foreach ($tables as $table) {
            DB::Statement("ALTER TABLE {$prefix}{$table} ENGINE=InnoDB");
        } 
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{

	}

}
