<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePersonalInterests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('personal_interests', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->increments('personal_interest_id');
            $table->string('personal_interest_name', 50)->nullable();
            $table->string('personal_interest_value', 100)->nullable();
            $table->timestamps();

            $table->index(array('personal_interest_name'), 'user_personal_interest_name_idx');
            $table->index(array('personal_interest_value'), 'user_personal_interest_value_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('personal_interests');
    }
}
