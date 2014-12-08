<?php
/**
 * Add indexes to the media table and change the table engine to innodb
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToMediaAndChangeDbEngine extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('media', function(Blueprint $table)
        {
            $table->index(array('media_name_id'), 'media_nameid_idx');
            $table->index(array('media_name_long'), 'media_name_long_idx');
            $table->index(array('object_id'), 'objectid_idx');
            $table->index(array('object_name'), 'objectid_name_idx');
            $table->index(array('file_name'), 'file_name_idx');
            $table->index(array('file_extension'), 'file_extension_idx');
            $table->index(array('file_size'), 'file_size_idx');
            $table->index(array('path'), 'path_idx');
            $table->index(array('realpath'), 'realpath_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('object_id', 'object_name'), 'objectid_object_name_idx');
            $table->index(array('object_id', 'object_name', 'media_name_id'), 'objectid_object_name_media_name_idx');
        });

        // Change the table engine to InnoDB
        $prefix = DB::getTablePrefix();
        DB::statement("ALTER TABLE {$prefix}media ENGINE=InnoDB");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('media', function(Blueprint $table)
        {
            $table->dropIndex('media_nameid_idx');
            $table->dropIndex('media_name_long_idx');
            $table->dropIndex('objectid_idx');
            $table->dropIndex('objectid_name_idx');
            $table->dropIndex('file_name_idx');
            $table->dropIndex('file_extension_idx');
            $table->dropIndex('file_size_idx');
            $table->dropIndex('path_idx');
            $table->dropIndex('realpath_idx');
            $table->dropIndex('modified_by_idx');
            $table->dropIndex('objectid_object_name_idx');
            $table->dropIndex('objectid_object_name_media_name_idx');
        });
    }
}
