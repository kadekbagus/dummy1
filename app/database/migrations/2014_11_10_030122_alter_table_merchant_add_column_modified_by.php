<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantAddColumnModifiedBy extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->bigInteger('modified_by')->unsigned()->default(0)->after('parent_id');
            $table->index(array('modified_by'), 'modified_by_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropIndex('modified_by_idx');
            $table->dropColumn('modified_by');
        });
    }

}
