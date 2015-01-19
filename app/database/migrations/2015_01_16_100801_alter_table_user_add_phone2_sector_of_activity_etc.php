<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableUserAddPhone2SectorOfActivityEtc extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::getPdo()->beginTransaction();

        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}user_details` CHANGE COLUMN `phone` `phone1` VARCHAR(50) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL");
        DB::Statement("ALTER TABLE `{$prefix}user_details` CHANGE COLUMN `annual_salary_range1` `avg_annual_income1` DECIMAL(16,2) NULL DEFAULT '0.00' ,
                                                           CHANGE COLUMN `annual_salary_range2` `avg_annual_income2` DECIMAL(16,2) NULL DEFAULT '0.00'");

        Schema::table('user_details', function(Blueprint $table)
        {
            $table->string('phone2', '50')->nullable()->after('phone1');
            $table->string('sector_of_activity', '100')->nullable()->after('occupation');
            $table->string('company_name', '100')->nullable()->after('sector_of_activity');
            $table->decimal('avg_monthly_spent1', 16, 2)->nullable()->after('avg_annual_income2');
            $table->decimal('avg_monthly_spent2', 16, 2)->nullable()->after('avg_monthly_spent1');

            $table->index(array('avg_monthly_spent1'), 'avg_monthly_spent1_idx');
            $table->index(array('avg_monthly_spent2'), 'avg_monthly_spent2_idx');
        });

        DB::getPdo()->commit();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::getPdo()->beginTransaction();

        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}user_details` CHANGE COLUMN `phone1` `phone` VARCHAR(50) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL");
        DB::Statement("ALTER TABLE `{$prefix}user_details` CHANGE COLUMN `avg_annual_income1` `annual_salary_range1` DECIMAL(16,2) NULL DEFAULT '0.00' ,
                                                           CHANGE COLUMN `avg_annual_income2` `annual_salary_range2` DECIMAL(16,2) NULL DEFAULT '0.00'");

        Schema::table('user_details', function(Blueprint $table)
        {
            $table->dropIndex('avg_monthly_spent1_idx');
            $table->dropIndex('avg_monthly_spent2_idx');
            $table->dropColumn('phone2');
            $table->dropColumn('sector_of_activity');
            $table->dropColumn('company_name');
            $table->dropColumn('avg_monthly_spent1');
            $table->dropColumn('avg_monthly_spent2');
        });

        DB::getPdo()->commit();
    }
}
