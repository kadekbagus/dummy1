<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableUsers extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function(Blueprint $table)
        {
            $table->bigInteger('user_id')->unsigned();
            $table->string('username', 50);
            $table->string('user_password', 100);
            $table->string('user_email', 255);
            $table->string('user_firstname', 50);
            $table->string('user_lastname', 75);
            $table->string('user_status', 20);
            $table->dateTime('user_last_login');
            $table->string('user_ip', 45);
            $table->integer('user_role_id')->unsigned();
            $table->string('status', 15);
            $table->bigInteger('modified_by')->unsigned();
            $table->timestamps();
            $table->primary(array('user_id'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('users');
    }

}
