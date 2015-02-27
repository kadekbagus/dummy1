<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableActivitiesAddPromotionCouponEventProductColumn extends Migration
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
            $table->string('full_name', 100)->nullable()->default(NULL)->after('user_email');
            $table->bigInteger('product_id')->nullable()->default(NULL)->after('object_name');
            $table->string('product_name', 100)->nullable()->default(NULL)->after('product_id');
            $table->bigInteger('coupon_id')->nullable()->default(NULL)->after('product_name');
            $table->string('coupon_name', 100)->nullable()->default(NULL)->after('coupon_id');
            $table->bigInteger('promotion_id')->nullable()->default(NULL)->after('coupon_name');
            $table->string('promotion_name', 100)->nullable()->default(NULL)->after('promotion_id');
            $table->bigInteger('event_id')->nullable()->default(NULL)->after('promotion_name');
            $table->string('event_name', 100)->nullable()->default(NULL)->after('event_id');

            $table->index(['full_name'], 'full_name_idx');
            $table->index(['product_id'], 'productid_idx');
            $table->index(['product_name'], 'product_name_idx');
            $table->index(['coupon_id'], 'couponid_idx');
            $table->index(['coupon_name'], 'coupon_name_idx');
            $table->index(['promotion_id'], 'promotionid_idx');
            $table->index(['promotion_name'], 'promotion_name_idx');
            $table->index(['event_id'], 'eventid_idx');
            $table->index(['event_name'], 'event_name_idx');
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
            $table->dropIndex('full_name_idx');
            $table->dropIndex('productid_idx');
            $table->dropIndex('product_name_idx');
            $table->dropIndex('couponid_idx');
            $table->dropIndex('coupon_name_idx');
            $table->dropIndex('promotionid_idx');
            $table->dropIndex('promotion_name_idx');
            $table->dropIndex('eventid_idx');
            $table->dropIndex('event_name_idx');

            $table->dropColumn('full_name');
            $table->dropColumn('product_id');
            $table->dropColumn('product_name');
            $table->dropColumn('coupon_id');
            $table->dropColumn('coupon_name');
            $table->dropColumn('promotion_id');
            $table->dropColumn('promotion_name');
            $table->dropColumn('event_id');
            $table->dropColumn('event_name');
        });
    }

}
