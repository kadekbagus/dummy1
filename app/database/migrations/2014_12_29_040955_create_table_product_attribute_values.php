<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableProductAttributeValues extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_attribute_values', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->increments('product_attribut_value_id');
            $table->integer('product_attribute_id');
            $table->string('value', '100');
            $table->timestamps();

            $table->index(array('product_attribute_id'), 'product_attributeid_idx');
            $table->index(array('value'), 'value_idx');
            $table->index(array('product_attribute_id', 'value'), 'product_attributeid_value_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('product_attribute_values');
    }
}
