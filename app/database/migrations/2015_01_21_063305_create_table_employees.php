<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableEmployees extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employees', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('employee_id');
            $table->bigInteger('user_id')->unsigned();
            $table->string('employee_id_char', '50')->nullable();
            $table->string('position', '50')->nullable();
            $table->string('status', '15')->nullable()->default('active');
            $table->timestamps();

            $table->index(array('user_id'), 'userid_idx');
            $table->index(array('employee_id_char'), 'employee_id_char_idx');
            $table->index(array('position'), 'position_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('created_at'), 'created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('employees');
    }

}
