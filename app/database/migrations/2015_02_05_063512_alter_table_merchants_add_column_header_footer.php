<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddColumnHeaderFooter extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function(Blueprint $table)
        {
            $table->text('ticket_footer')->nullable()->after('mobile_default_language');
            $table->text('ticket_header')->nullable()->after('mobile_default_language');
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
            $table->dropColumn('ticket_header');
            $table->dropColumn('ticket_footer');
        });
    }

}
