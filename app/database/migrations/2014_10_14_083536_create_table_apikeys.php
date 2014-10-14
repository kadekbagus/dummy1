<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableApikeys extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('apikeys', function(Blueprint $table)
		{
			$table->bigInteger('apikey_id')->unsigned();
			$table->string('api_key',100);
			$table->string('api_secret_key',255);
			$table->bigInteger('user_id')->unsigned();
			$table->string('status',15);
			$table->timestamps();
			$table->primary(array('apikey_id'));
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('apikeys');
	}

}
