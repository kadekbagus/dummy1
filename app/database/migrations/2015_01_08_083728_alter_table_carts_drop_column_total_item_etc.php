<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableCartsDropColumnTotalItemEtc extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('carts', function(Blueprint $table)
		{
            $table->dropIndex('subtotal_idx');
            $table->dropIndex('vat_idx');
            $table->dropIndex('totaltopay_idx');

            $table->dropColumn('subtotal');
            $table->dropColumn('vat');
            $table->dropColumn('total_to_pay');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('carts', function(Blueprint $table)
		{
			$table->decimal('subtotal', 16, 2)->nullable();
            $table->decimal('vat', 16, 2)->nullable();
            $table->decimal('total_to_pay', 16, 2)->nullable();

            $table->index(array('subtotal'), 'subtotal_idx');
            $table->index(array('vat'), 'vat_idx');
            $table->index(array('total_to_pay'), 'totaltopay_idx');
		});
	}

}
