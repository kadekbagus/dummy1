<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableMacAddressesLikeMall extends Migration {

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

		$builder->create('mac_addresses', function(OrbitBlueprint $table)
		{
			$table->encodedId('mac_address_id');
            $table->string('user_email', 255);
            $table->char('mac_address', 18);
            $table->char('ip_address', 15)->nullable()->default(NULL);
            $table->timestamps();

            $table->primary('mac_address_id');
            $table->index(array('user_email'), 'user_email_idx');
            $table->index(array('mac_address'), 'mac_address_idx');
            $table->index(array('user_email', 'mac_address'), 'user_email_mac_address_idx');
            $table->index(array('created_at'), 'created_at_idx');
            $table->index(['ip_address'], 'ip_address_idx');
            $table->index(['ip_address', 'mac_address'], 'mac_ip_address_idx');
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('mac_addresses');
	}

}
