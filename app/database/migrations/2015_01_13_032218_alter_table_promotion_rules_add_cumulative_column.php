<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablePromotionRulesAddCumulativeColumn extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('promotion_rules', function(Blueprint $table)
        {
            $table->char('is_cumulative_with_promotions', 1)->nullable()->default('N')->after('discount_value');
            $table->char('is_cumulative_with_coupons', 1)->nullable()->default('N')->after('discount_value');
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promotion_rules', function(Blueprint $table)
        {
            $table->dropColumn('is_cumulative_with_coupons');
            $table->dropColumn('is_cumulative_with_promotions');
        });
    }

}
