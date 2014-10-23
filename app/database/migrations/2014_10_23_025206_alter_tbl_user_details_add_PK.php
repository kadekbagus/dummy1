<?php

use Illuminate\Database\Migrations\Migration;

class AlterTblUserDetailsAddPK extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` MODIFY `user_detail_id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` MODIFY `user_detail_id` BIGINT UNSIGNED AUTO_INCREMENT');
    }

}
