<?php

use Illuminate\Database\Migrations\Migration;

class AlterTableChangeEngineInnodb extends Migration
{
    /**
	 * Run the migrations.
	 *
	 * @return void
	 */
    public function up()
    {
        // Change the engine to InnoDB
        DB::Statement('ALTER TABLE `' . DB::getTablePrefix() . 'merchant_taxes` ENGINE=InnoDB');
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
