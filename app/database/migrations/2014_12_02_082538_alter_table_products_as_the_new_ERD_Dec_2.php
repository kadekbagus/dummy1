<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProductsAsTheNewERDDec2 extends Migration 
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // modifies some columns
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'products` MODIFY `merchant_id` INT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'products` MODIFY `product_name` VARCHAR(255) NOT NULL');

        Schema::table('products', function(Blueprint $table)
        {
            // add some columns
            $table->string('upc_code', 100)->nullable()->after('product_code');
            $table->char('is_featured', 1)->nullable()->after('long_description');
            $table->dateTime('new_from')->nullable()->after('is_featured');
            $table->string('in_store_localization', 255)->nullable()->after('new_until');
            $table->text('post_sales_url')->nullable()->after('in_store_localization');
            $table->integer('merchant_tax_id1')->unsigned()->nullable()->after('price');
            $table->integer('merchant_tax_id2')->unsigned()->nullable()->after('merchant_tax_id1');
            // remove old indexes
            $table->dropIndex('tax_code_idx');
            $table->dropIndex('stock_idx');
            $table->dropIndex('is_new_idx');
            $table->dropIndex('retailerid_idx');
            $table->dropIndex('depend_on_stock_idx');
            $table->dropIndex('is_new_status_idx');
            $table->dropIndex('retailerid_status_idx');
            $table->dropIndex('price_retailerid_status_idx');
            // remove some columns
            $table->dropColumn('retailer_id');
            $table->dropColumn('tax_code');
            $table->dropColumn('stock');
            $table->dropColumn('depend_on_stock');
            $table->dropColumn('is_new');
            // add some indexes
            $table->unique('product_code');
            $table->unique('upc_code');
            $table->index(array('upc_code'), 'upc_code_idx');
            $table->index(array('merchant_id', 'is_featured'), 'merchantid_isfeatured_idx');
            $table->index(array('is_featured'), 'isfeatured_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // modifies some columns
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'products` MODIFY `merchant_id` INT NULL');
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'products` MODIFY `product_name` VARCHAR(100) NOT NULL');

        Schema::table('products', function(Blueprint $table)
        {
            // remove some indexes
            $table->dropIndex('products_product_code_unique');
            $table->dropIndex('products_upc_code_unique');
            $table->dropIndex('upc_code_idx');
            $table->dropIndex('merchantid_isfeatured_idx');
            $table->dropIndex('isfeatured_idx');
            // remove some columns
            $table->dropColumn('upc_code');
            $table->dropColumn('is_featured');
            $table->dropColumn('new_from');
            $table->dropColumn('in_store_localization');
            $table->dropColumn('post_sales_url');
            $table->dropColumn('merchant_tax_id1');
            $table->dropColumn('merchant_tax_id2');
            // add old columns
            $table->integer('retailer_id');
            $table->string('tax_code', 15)->nullable();
            $table->integer('stock')->nullable()->unsigned();
            $table->char('depend_on_stock', 3);
            $table->char('is_new', 3);
            // add old indexes
            $table->index(array('tax_code'), 'tax_code_idx');
            $table->index(array('stock'), 'stock_idx');
            $table->index(array('is_new'), 'is_new_idx');
            $table->index(array('retailer_id'), 'retailerid_idx');
            $table->index(array('depend_on_stock'), 'depend_on_stock_idx');
            $table->index(array('is_new', 'status'), 'is_new_status_idx');
            $table->index(array('retailer_id', 'status'), 'retailerid_status_idx');
            $table->index(array('price', 'retailer_id', 'status'), 'price_retailerid_status_idx');
        });
    }

}
