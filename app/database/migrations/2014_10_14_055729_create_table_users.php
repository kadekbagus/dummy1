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
            $table->index(array('username'), 'username_idx');
            $table->index(array('username', 'user_password'), 'username_pwd_idx');
            $table->index(array('user_email'), 'email_idx');
            $table->index(array('username', 'user_password', 'status'), 'username_pwd_status_idx');
            $table->index(array('user_ip'), 'user_ip_idx');
            $table->index(array('user_role_id'), 'user_role_id_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('created_at'), 'created_at_idx');
            $table->index(array('updated_at'), 'updated_at_idx');
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
