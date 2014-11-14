<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableUserDetailsR201411141745 extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_details', function(Blueprint $table)
        {
            $table->char('preferred_language', 2)->nullable()->default('en')->after('photo');
            $table->string('occupation', 30)->nullable()->after('preferred_language');
            $table->string('last_education_degree', 20)->nullable()->after('occupation');
            $table->decimal('annual_salary_range1', 16, 2)->nullable()->default(0.00)->after('last_education_degree');
            $table->decimal('annual_salary_range2', 16, 2)->nullable()->default(0.00)->after('annual_salary_range1');
            $table->char('has_children', 1)->nullable()->after('annual_salary_range2');
            $table->smallInteger('number_of_children')->unsigned()->nullable()->after('has_children');
            $table->string('car_model', 30)->nullable()->after('number_of_children');
            $table->mediumInteger('car_year')->unsigned()->nullable()->after('car_model');

            $table->index(array('preferred_language'), 'remember_token_idx');
            $table->index(array('annual_salary_range1'), 'annual_salary_range1_idx');
            $table->index(array('annual_salary_range2'), 'annual_salary_range2_idx');
            $table->index(array('annual_salary_range1', 'annual_salary_range2'), 'annual_salary_range12_idx');
            $table->index(array('has_children'), 'has_children_idx');
            $table->index(array('number_of_children'), 'number_of_children_idx');
            $table->index(array('has_children', 'number_of_children'), 'has_children_number_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_details', function(Blueprint $table)
        {
            $table->dropIndex('remember_token_idx');
            $table->dropIndex('annual_salary_range1_idx');
            $table->dropIndex('annual_salary_range2_idx');
            $table->dropIndex('annual_salary_range12_idx');
            $table->dropIndex('has_children_idx');
            $table->dropIndex('number_of_children_idx');
            $table->dropIndex('has_children_number_idx');

            $table->dropColumn('preferred_language');
            $table->dropColumn('occupation');
            $table->dropColumn('last_education_degree');
            $table->dropColumn('annual_salary_range1');
            $table->dropColumn('annual_salary_range2');
            $table->dropColumn('has_children');
            $table->dropColumn('number_of_children');
            $table->dropColumn('car_model');
            $table->dropColumn('car_year');
        });
    }

}
