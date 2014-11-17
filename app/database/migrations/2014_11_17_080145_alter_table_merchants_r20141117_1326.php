<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsR201411171326 extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('merchants', function(Blueprint $table)
        {
        	$table->integer('postal_code')->nullable()->after('address_line3');
        	$table->string('contact_person_name', 50)->nullable()->after('vat_included');
        	$table->string('contact_person_position', 30)->nullable()->after('contact_person_name');
        	$table->string('contact_person_phone', 50)->nullable()->after('contact_person_position');
        	$table->string('sector_of_activity', 100)->nullable()->after('contact_person_phone');

        	$table->index(array('sector_of_activity'), 'merchant_sector_of_activity_idx');
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('merchants', function(Blueprint $table)
        {
		    $table->dropIndex('merchant_sector_of_activity_idx');
		    
		    $table->dropColumn('postal_code');
        	$table->dropColumn('contact_person_name');
        	$table->dropColumn('contact_person_position');
        	$table->dropColumn('contact_person_phone');
        	$table->dropColumn('sector_of_activity');
        });
	}

}
