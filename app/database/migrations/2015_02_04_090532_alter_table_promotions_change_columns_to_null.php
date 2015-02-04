<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablePromotionsChangeColumnsToNull extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}promotions`
                       CHANGE COLUMN `maximum_issued_coupon` `maximum_issued_coupon` INT(11) NULL ,
                       CHANGE COLUMN `coupon_validity_in_days` `coupon_validity_in_days` INT(11) NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}promotions`
                       CHANGE COLUMN `maximum_issued_coupon` `maximum_issued_coupon` INT(11) NOT NULL ,
                       CHANGE COLUMN `coupon_validity_in_days` `coupon_validity_in_days` INT(11) NOT NULL");
    }

}
