<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableTransactionModifyTransactionid extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::Statement('ALTER TABLE `' . DB::getTablePrefix() . 'transactions` AUTO_INCREMENT = 111111');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::Statement('ALTER TABLE `' . DB::getTablePrefix() . 'transactions` AUTO_INCREMENT = 0');
    }

}
