<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIncrementsToApikeyId extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'apikeys` MODIFY `apikey_id` BIGINT UNSIGNED AUTO_INCREMENT');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'apikeys` MODIFY `status` VARCHAR(15) DEFAULT "active" COMMENT "valid: active, blocked, deleted"');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'apikeys` MODIFY `apikey_id` BIGINT UNSIGNED');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'apikeys` MODIFY `status` VARCHAR(15)');
    }

}
