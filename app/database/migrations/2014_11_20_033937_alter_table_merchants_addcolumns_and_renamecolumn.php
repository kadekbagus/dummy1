<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddcolumnsAndRenamecolumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'merchants` CHANGE `contact_person_name` `contact_person_firstname` VARCHAR(75)');

            $table->string('contact_person_lastname', 75)->nullable()->after('contact_person_firstname');
            $table->string('contact_person_phone2', 50)->nullable()->after('contact_person_phone');
            $table->string('contact_person_email', 255)->nullable()->after('contact_person_phone2');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'merchants` CHANGE `contact_person_firstname` `contact_person_name` VARCHAR(50)');

            $table->dropColumn('contact_person_lastname');
            $table->dropColumn('contact_person_phone2');
            $table->dropColumn('contact_person_email');
        });
    }

}
