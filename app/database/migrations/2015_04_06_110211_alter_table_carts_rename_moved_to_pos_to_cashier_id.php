<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableCartsRenameMovedToPosToCashierId extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('carts', function(Blueprint $table)
        {
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'carts` CHANGE `moved_to_pos` `cashier_id` BIGINT UNSIGNED NULL');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('carts', function(Blueprint $table)
        {
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'carts` CHANGE `cashier_id` `moved_to_pos` CHAR(1) NULL DEFAULT "N"');
        });
    }

}
