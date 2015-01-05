<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableAddcolumnOnTransactionTable extends Migration
{
    /**
	 * Run the migrations.
	 *
	 * @return void
	 */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('tendered', 16, 2)->nullable()->after('total_to_pay');
            $table->decimal('change', 16, 2)->nullable()->after('tendered');
            $table->index(array('tendered'), 'tendered_idx');
            $table->index(array('change'), 'change_idx');
        });
    }

    /**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('tendered_idx');
            $table->dropIndex('change_idx');
            $table->dropColumn('tendered');
            $table->dropColumn('change');
        });
    }

}
