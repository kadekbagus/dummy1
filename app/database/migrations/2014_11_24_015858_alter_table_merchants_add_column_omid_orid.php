<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddColumnOmidOrid extends Migration
{
    /**
	 * Run the migrations.
	 *
	 * @return void
	 */
    public function up()
    {
       Schema::table('merchants', function (Blueprint $table) {
            $table->string('omid', 100)->after('merchant_id');
            $table->string('orid', 100)->after('omid');

            $table->index(array('omid'), 'omid_idx');
            $table->index(array('orid'), 'orid_idx');
       });
    }

    /**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropIndex('omid_idx');
            $table->dropIndex('orid_idx');

            $table->dropColumn('omid');
            $table->dropColumn('orid');
        });
    }

}
