<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUniqueConstraintToApikeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('apikeys', function (Blueprint $table) {
            $table->unique(array('api_key'), 'api_key_unique');
            $table->unique(array('api_secret_key'), 'api_secret_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('apikeys', function (Blueprint $table) {
            $table->dropUnique('api_key_unique');
            $table->dropUnique('api_secret_key_unique');
        });
    }

}
