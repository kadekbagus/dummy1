<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableTokens extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tokens', function(Blueprint $table)
        {
            $table->bigIncrements('token_id');
            $table->string('token_name', 100);
            $table->string('token_value', 1000);
            $table->string('status', 15)->default('active');
            $table->string('email', 255)->nullable();
            $table->datetime('expire')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->bigInteger('object_id')->nullable();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
        DB::Statement('ALTER TABLE `' . DB::getTablePrefix() . 'tokens` ENGINE=InnoDB');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('tokens');
    }

}
