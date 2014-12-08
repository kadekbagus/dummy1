<?php
/**
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableMedia extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('media', function(Blueprint $table)
        {
            $table->bigIncrements('media_id');
            $table->string('media_name_id', 50);
            $table->string('media_name_long', 255)->nullable();
            $table->biginteger('object_id')->unsigned()->nullable();
            $table->string('object_name', 100)->nullable();
            $table->String('file_name', 255)->nullable();
            $table->string('file_extension', 10)->nullable();
            $table->biginteger('file_size')->unsigned()->nullable();
            $table->string('mime_type', 50)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('realpath', 2000)->nullable();
            $table->text('metadata')->nullable();
            $table->biginteger('modified_by')->unsigned()->nullable();
            $table->datetime('created_at')->nullable();
            $table->datetime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('media');
    }
}
