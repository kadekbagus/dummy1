<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductsDropUniqueIndexProductCodeUpcCode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}products`
                        DROP INDEX `products_upc_code_unique` ,
                        DROP INDEX `products_product_code_unique`");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = DB::getTablePrefix();
        DB::Statement("ALTER TABLE `{$prefix}products`
                        ADD UNIQUE INDEX `product_code_UNIQUE` (`product_code` ASC),
                        ADD UNIQUE INDEX `upc_code_UNIQUE` (`upc_code` ASC)");
    }
}
