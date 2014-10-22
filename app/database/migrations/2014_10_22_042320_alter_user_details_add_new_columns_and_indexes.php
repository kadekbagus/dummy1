<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUserDetailsAddNewColumnsAndIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_details', function (Blueprint $table) {
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` ADD COLUMN `user_detail_id` BIGINT UNSIGNED NOT NULL FIRST ');
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` MODIFY `user_id` BIGINT UNSIGNED NOT NULL');
            // $table->bigInteger('user_detail_id')->unsigned();
            $table->integer('province_id')->unsigned()->nullable()->after('city');
            $table->string('province', 100)->nullable()->after('province_id');;
            $table->index(array('province_id'), 'province_id_idx');
            $table->index(array('province'), 'province_idx');
            $table->index(array('number_visit_all_shop', 'province_id'), 'number_visit_provinceid_idx');
            $table->index(array('number_visit_all_shop', 'province'), 'number_visit_province_idx');
            $table->index(array('amount_spent_all_shop', 'province_id'), 'amount_spent_provinceid_idx');
            $table->index(array('amount_spent_all_shop', 'province'), 'amount_spent_province_idx');
            $table->index(array('average_spent_per_month_all_shop', 'province_id'), 'average_spent_provinceid_idx');
            $table->index(array('average_spent_per_month_all_shop', 'province'), 'average_spent_province_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropIndex('province_id_idx');
            $table->dropIndex('province_idx');
            $table->dropIndex('number_visit_provinceid_idx');
            $table->dropIndex('number_visit_province_idx');
            $table->dropIndex('amount_spent_provinceid_idx');
            $table->dropIndex('amount_spent_province_idx');
            $table->dropIndex('average_spent_provinceid_idx');
            $table->dropIndex('average_spent_province_idx');
            $table->dropColumn('user_detail_id');
            $table->dropColumn('province_id');
            $table->dropColumn('province');
        });
    }

}
