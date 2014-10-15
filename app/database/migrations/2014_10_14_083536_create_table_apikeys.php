<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableApikeys extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('apikeys', function(Blueprint $table)
        {
            $table->bigInteger('apikey_id')->unsigned();
            $table->string('api_key', 100);
            $table->string('api_secret_key', 255);
            $table->bigInteger('user_id')->unsigned();
            $table->string('status', 15);
            $table->timestamps();
            $table->primary(array('apikey_id'));
            $table->index(array('api_key'), 'api_key_idx');
            $table->index(array('user_id'), 'user_id_idx');
            $table->index(array('api_key', 'user_id'), 'api_key_user_idx');
            $table->index(array('api_key', 'status'), 'api_key_status_idx');
            $table->index(array('api_key', 'user_id', 'status'), 'api_key_user_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('apikeys');
    }

}
