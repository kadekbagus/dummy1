<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropTableProductCategory extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::drop('product_category');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('product_category', function(Blueprint $table)
        {
            $table->increments('product_category_id')->unsigned();
            $table->integer('category_id')->unsigned();
            $table->bigInteger('product_id')->unsigned();
            $table->timestamps();
            $table->index(array('category_id'), 'categoryid_idx');
            $table->index(array('product_id'), 'productid_idx');
            $table->index(array('category_id', 'product_id'), 'categoryid_productid_idx');

            $table->integer('category_family_level')->unsigned()->nullable();
            $table->index(array('category_family_level'), 'category_family_level_idx');
            $table->index(array('category_id', 'category_family_level'), 'categoryid_family_level_idx');
        });
    }

}
