<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTablePromotionRules extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotion_rules', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->increments('promotion_rule_id');
            $table->integer('promotion_id')->unsigned();
            $table->string('rule_type', 50)->nullable();
            $table->decimal('rule_value', 16, 2)->nullable()->default('0');
            $table->string('rule_object_type', 50)->nullable();
            $table->bigInteger('rule_object_id1')->unsigned()->nullable();
            $table->bigInteger('rule_object_id2')->unsigned()->nullable();
            $table->bigInteger('rule_object_id3')->unsigned()->nullable();
            $table->bigInteger('rule_object_id4')->unsigned()->nullable();
            $table->bigInteger('rule_object_id5')->unsigned()->nullable();
            $table->string('discount_object_type', 50)->nullable();
            $table->bigInteger('discount_object_id1')->unsigned()->nullable();
            $table->bigInteger('discount_object_id2')->unsigned()->nullable();
            $table->bigInteger('discount_object_id3')->unsigned()->nullable();
            $table->bigInteger('discount_object_id4')->unsigned()->nullable();
            $table->bigInteger('discount_object_id5')->unsigned()->nullable();
            $table->decimal('discount_value', 16, 4)->nullable()->default('0');
            $table->decimal('coupon_redeem_rule_value', 16, 2)->nullable()->default('0');
            $table->timestamps();

            $table->index(array('promotion_id'), 'promotion_id_idx');
            $table->index(array('rule_type'), 'rule_type_idx');
            $table->index(array('rule_object_id1'), 'rule_object_id1_idx');
            $table->index(array('discount_object_id1'), 'discount_object_id1_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('promotion_rules');
    }

}
