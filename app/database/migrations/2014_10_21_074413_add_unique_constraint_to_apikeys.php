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
            $table->unique('api_key');
            $table->unique('api_secret_key');
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
            $table->dropUnique('api_key');
            $table->dropUnique('api_secret_key');
        });
    }

}
