<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTablePromotionAddMallIdAndAllRetailer extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->bigInteger('location_id')->unsigned()->default(0)->after('is_coupon');
            $table->string('location_type', 32)->after('location_id');
            $table->string('is_all_retailer', 1)->after('location_type');
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
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex('location_type_idx');
            $table->dropIndex('location_id_idx');
            $table->dropColumn('location_id');
            $table->dropColumn('location_type');
            $table->dropColumn('is_all_retailer');
        });
    }

}
