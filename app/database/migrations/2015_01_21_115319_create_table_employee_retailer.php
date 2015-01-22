<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableEmployeeRetailer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employee_retailer', function(Blueprint $table)
        {
            $table->bigIncrements('employee_retailer_id');
            $table->bigInteger('employee_id')->unsigned();
            $table->bigInteger('retailer_id')->unsigned();
            $table->timestamps();

            $table->index(array('employee_id'), 'employeeid_idx');
            $table->index(array('retailer_id'), 'retaileridx_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('employee_retailer');
    }
}
