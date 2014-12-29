<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductAttributeValuesIdUnsigned extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = DB::getTablePrefix();
        DB::statement("ALTER TABLE `{$prefix}product_attribute_values` MODIFY `product_attribute_id` INT(11) UNSIGNED NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_attribute_values', function(Blueprint $table)
        {
            $prefix = DB::getTablePrefix();
            DB::statement("ALTER TABLE `{$prefix}product_attribute_values` MODIFY `product_attribute_id` INT(11) NOT NULL");
        });
    }
}
