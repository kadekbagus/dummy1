<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableLuckyDrawReceiptsLikeMall extends Migration {

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

		$builder->create('lucky_draw_receipts', function(OrbitBlueprint $table)
		{
            $table->engine = 'InnoDB';
            $table->encodedId('lucky_draw_receipt_id');
            $table->encodedId('mall_id');
            $table->encodedId('user_id')->nullable();
            $table->encodedId('receipt_retailer_id')->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->datetime('receipt_date')->nullable();
            $table->string('receipt_payment_type', 30)->nullable();
            $table->string('receipt_card_number', 30)->nullable();
            $table->decimal('receipt_amount', 16, 2)->nullable()->default('0');
            $table->string('receipt_group', 40)->nullable();
            $table->string('external_receipt_id', 50)->nullable();
            $table->string('external_retailer_id', 30)->nullable();
            $table->string('object_type', 15)->nullable();
            $table->string('status', 15)->nullable();
            $table->encodedId('created_by')->nullable();
            $table->encodedId('modified_by')->nullable();
            $table->timestamps();

            $table->primary('lucky_draw_receipt_id');
            $table->index(array('mall_id'), 'mall_id_idx');
            $table->index(array('user_id'), 'user_id_idx');
            $table->index(array('receipt_retailer_id'), 'receipt_retailer_id_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('created_at'), 'created_at_idx');
            $table->index(array('object_type'), 'object_type_idx');
            $table->index(['receipt_group'], 'receipt_group_idx');
            $table->index(['external_receipt_id'], 'external_receipt_id_idx');
            $table->index(['external_retailer_id'], 'external_retailer_idx');

		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('lucky_draw_receipts');
	}

}
