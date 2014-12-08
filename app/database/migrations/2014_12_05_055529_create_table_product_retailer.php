<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableProductRetailer extends Migration
{
    /**
	 * Run the migrations.
	 *
	 * @return void
	 */
    public function up()
    {
        Schema::create('product_retailer', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('product_retailer_id')->unsigned();
            $table->bigInteger('product_id')->unsigned();
            $table->integer('retailer_id')->unsigned();
            $table->timestamps();

            $table->index(array('product_id'), 'productid_idx');
            $table->index(array('retailer_id'), 'retailerid_idx');
            $table->unique(array('product_id', 'retailer_id'), 'productid_retailerid_UNIQUE');
        });
    }

    /**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
    public function down()
    {
        Schema::drop('product_retailer');
    }

}
