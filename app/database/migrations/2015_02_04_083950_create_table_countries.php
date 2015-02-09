<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableCountries extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('countries', function(Blueprint $table)
        {
            $table->increments('country_id');
            $table->string('name', 75);
            $table->char('code', 2);
            $table->timestamps();

            $table->index(array('name'), 'name_idx');
            $table->index(array('code'), 'code_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('countries');
    }
}
