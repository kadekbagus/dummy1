<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableLuckyDrawNumberReceiptLikeMall extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		$builder = DB::connection()->getSchemaBuilder();
		$builder->blueprintResolver(function ($table, $callback) {
			return new OrbitBlueprint($table, $callback);
		});

		$builder->create('lucky_draw_number_receipt', function(Blueprint $table)
		{
			$table->encodedId('lucky_draw_number_receipt_id');
			$table->encodedId('lucky_draw_number_id');
			$table->encodedId('lucky_draw_receipt_id');

			$table->primary('lucky_draw_number_receipt_id');
            $table->index(['lucky_draw_number_id', 'lucky_draw_receipt_id'], 'luckydrawnumberid_luckydrawreceiptid_idx');
            $table->index(['lucky_draw_receipt_id'], 'lucky_draw_receipt_id_idx');
            $table->index(['lucky_draw_number_id'], 'lucky_draw_number_id_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('lucky_draw_number_receipt');
	}

}
