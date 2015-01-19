<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableUserPersonalInterest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::getPdo()->beginTransaction();
        Schema::create('user_personal_interest', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('user_personal_interest_id');
            $table->bigInteger('user_id')->unsigned();
            $table->integer('personal_interest_id')->unsigned();
            $table->string('personal_interest_name', 50)->nullable();
            $table->string('personal_interest_value', 100)->nullable();
            $table->timestamps();

            $table->index(array('user_id'), 'userid_idx');
            $table->index(array('personal_interest_id'), 'personalid_idx');
            $table->index(array('user_id', 'personal_interest_id'), 'user_personal_idx');
            $table->index(array('personal_interest_name'), 'user_personal_interest_name_idx');
            $table->index(array('personal_interest_value'), 'user_personal_interest_value_idx');
        });
        DB::getPdo()->commit();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::getPdo()->beginTransaction();
        Schema::drop('user_personal_interest');
        DB::getPdo()->commit();
    }
}
