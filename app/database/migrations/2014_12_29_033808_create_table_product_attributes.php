<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableProductAttributes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_attributes', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->increments('product_attribute_id');
            $table->string('product_attribute_name', '50')->nullable();
            $table->timestamps();

            $table->index(array('product_attribute_name'), 'product_attribute_name_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('product_attributes');
    }
}
