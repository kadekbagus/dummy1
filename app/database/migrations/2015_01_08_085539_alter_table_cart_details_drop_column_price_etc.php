<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableCartDetailsDropColumnPriceEtc extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('cart_details', function(Blueprint $table)
		{
			$table->dropIndex('price_idx');
            $table->dropIndex('upc_idx');
            $table->dropIndex('sku_idx');
            $table->dropIndex('productcode_idx');

            $table->dropColumn('price');
            $table->dropColumn('upc');
            $table->dropColumn('sku');
            $table->dropColumn('product_code');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('cart_details', function(Blueprint $table)
		{
			$table->decimal('price', 16, 2)->nullable();
            $table->string('product_code', 20)->nullable();
            $table->string('upc', 30)->nullable();
            $table->string('sku', 30)->nullable();

            $table->index(array('price'), 'price_idx');
            $table->index(array('product_code'), 'productcode_idx');
            $table->index(array('upc'), 'upc_idx');
            $table->index(array('sku'), 'sku_idx');
		});
	}

}
