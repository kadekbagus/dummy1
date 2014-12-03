<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductFixProductCode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
           DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'products` MODIFY `product_code` VARCHAR(20) NULL');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'products` MODIFY `product_code` VARCHAR(20) NOT NULL');
        });
    }

}
