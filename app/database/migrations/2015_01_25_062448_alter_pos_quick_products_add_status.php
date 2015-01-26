<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPosQuickProductsAddStatus extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}pos_quick_products`
                       CHANGE COLUMN `product_id` `product_id` BIGINT(20) UNSIGNED NOT NULL ,
                       CHANGE COLUMN `merchant_id` `merchant_id` BIGINT(20) UNSIGNED NOT NULL ,
                       CHANGE COLUMN `product_order` `product_order` INT UNSIGNED NOT NULL");
        Schema::table('pos_quick_products', function(Blueprint $table)
        {
            $table->string('status', 15)->nullable()->default('active')->after('product_order');
            $table->index(array('status'), 'status_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}pos_quick_products`
                       CHANGE COLUMN `product_id` `product_id` BIGINT(20) NOT NULL ,
                       CHANGE COLUMN `merchant_id` `merchant_id` BIGINT(20) NOT NULL ,
                       CHANGE COLUMN `product_order` `product_order` BIGINT(20)  NOT NULL");
        Schema::table('pos_quick_products', function(Blueprint $table)
        {
            $table->dropIndex('status_idx');
            $table->dropColumn('status');
        });
    }
}
