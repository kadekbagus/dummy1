<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableInboxesLikeMall extends Migration {

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
		$builder->create('inboxes', function(OrbitBlueprint $table)
		{
            $table->engine = 'InnoDB';
            $table->encodedId('inbox_id');
            $table->encodedId('user_id')->default(null);
            $table->encodedId('from_id')->default(null);
            $table->string('from_name', 20)->nullable();

            $table->string('subject', 250)->nullable()->default(null);
            $table->text('content')->nullable()->default(null);
            $table->string('inbox_type', 20)->nullable();
            $table->char('is_read', 1)->nullable();

            $table->string('status', 15)->nullable();
            $table->encodedId('created_by')->nullable();
            $table->encodedId('modified_by')->nullable();
            $table->timestamps();

            $table->primary('inbox_id');
            $table->index(['user_id'], 'user_idx');
            $table->index(['from_id'], 'from_idx');
            $table->index(['from_name'], 'from_name_idx');
            $table->index(['status'], 'status_idx');
            $table->index(['inbox_type'], 'inbox_type_idx');
            $table->index(['is_read'], 'is_read_idx');

            $table->index(['is_read', 'status'], 'status_is_read_idx');
            $table->index(['is_read', 'inbox_type'], 'inbox_type_is_read_idx');
            $table->index(['is_read', 'inbox_type', 'status'], 'status_inbox_type_is_read_idx');

            $table->index(['created_by'], 'created_by_idx');
            $table->index(['modified_by'], 'modified_by_idx');
            $table->index(['created_at'], 'created_at_idx');

		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('inboxes');
	}

}
