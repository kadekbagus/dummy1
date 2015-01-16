<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablePersonalInterestsAddStatusAndCreatedAndModifiedBy extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('personal_interests', function(Blueprint $table)
        {
            $table->string('status', '15')->default('active')->after('personal_interest_value');
            $table->bigInteger('created_by')->nullable()->unsigned()->after('status');
            $table->bigInteger('modified_by')->nullable()->unsigned()->after('created_by');

            $table->index(array('status'), 'status_idx');
            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('personal_interest_id', 'status'), 'interestid_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('personal_interests', function(Blueprint $table)
        {
            $table->dropIndex('status_idx');
            $table->dropIndex('created_by_idx');
            $table->dropIndex('modified_by_idx');
            $table->dropIndex('interestid_status_idx');

            $table->dropColumn('status');
            $table->dropColumn('created_by');
            $table->dropColumn('modified_by');
        });
    }
}
