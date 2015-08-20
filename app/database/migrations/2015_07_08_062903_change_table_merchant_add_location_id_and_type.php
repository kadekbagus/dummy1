<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTableMerchantAddLocationIdAndType extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table("merchants", function (Blueprint $table) {
            $table->bigInteger('location_id')->unsigned()->default(0)->after('object_type');
            $table->string('location_type', 32)->after('location_id');
            $table->index(array('location_id'), 'location_id_idx');
            $table->index(array('location_type'), 'location_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table("merchants", function (Blueprint $table) {
            $table->dropIndex('location_id_idx');
            $table->dropIndex('location_type_idx');
            $table->dropColumn('location_id');
            $table->dropColumn('location_type');
        });
    }
}
