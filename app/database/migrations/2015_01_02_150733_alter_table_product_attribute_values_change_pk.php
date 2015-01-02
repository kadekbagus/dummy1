<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductAttributeValuesChangePk extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}product_attribute_values`
                       CHANGE COLUMN `product_attribut_value_id` `product_attribute_value_id`
                       INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}product_attribute_values`
                       CHANGE COLUMN `product_attribute_value_id` `product_attribut_value_id`
                       INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");
    }
}
