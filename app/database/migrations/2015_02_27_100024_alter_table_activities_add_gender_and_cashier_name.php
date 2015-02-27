<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableActivitiesAddGenderAndCashierName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities', function(Blueprint $table)
        {
            $table->string('staff_name', 100)->nullable()->after('staff_id')->default(NULL);
            $table->char('gender', 1)->nullable()->after('full_name')->default(NULL);

            $table->index(['staff_name'], 'staff_name_idx');
            $table->index(['gender'], 'gender_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activities', function(Blueprint $table)
        {
            $table->dropIndex('gender_idx');
            $table->dropIndex('staff_name_idx');
            $table->dropColumn('staff_name');
            $table->dropColumn('gender');
        });
    }

}
