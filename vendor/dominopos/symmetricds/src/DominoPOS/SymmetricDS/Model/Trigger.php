<?php namespace DominoPOS\SymmetricDS\Model;


/**
 * Class Trigger
 * @property string trigger_id
 * @property string source_catalog_name
 * @property string source_schema_name
 * @property string source_table_name
 * @property string channel_id
 * @property string reload_channel_id
 * @property string sync_on_update
 * @property string sync_on_insert
 * @property string sync_on_delete
 * @property string sync_on_incoming_batch
 * @property string name_for_update_trigger
 * @property string name_for_insert_trigger
 * @property string name_for_delete_trigger
 * @property string sync_on_update_condition
 * @property string sync_on_insert_condition
 * @property string sync_on_delete_condition
 * @property string custom_on_update_text
 * @property string custom_on_insert_text
 * @property string custom_on_delete_text
 * @property string external_select
 * @property string tx_id_expression
 * @property string channel_expression
 * @property string excluded_column_names
 * @property string sync_key_names
 * @property string use_stream_lobs
 * @property string use_capture_lobs
 * @property string use_capture_old_data
 * @property string use_handle_key_updates
 * @property string create_time
 * @property string last_update_by
 * @property string last_update_time
 * @package DominoPOS\SymmetricDS
 *
 */
class Trigger extends Base
{
    protected $table = 'trigger';

    protected $primaryKey = 'trigger_id';

    public function channel()
    {
        return $this->belongsTo(
            'DominoPOS\\SymmetricDS\\Model\\Channel',
            'channel_id',
            'channel_id'
        );
    }

    public function routers()
    {
        return $this->belongsToMany(
            'DominoPOS\\SymmetricDS\\Model\\Router',
            'trigger_router',
            'trigger_id',
            'router_id',
            'routers'
        )->withPivot([
            "enabled",
            "initial_load_order",
            "initial_load_select",
            "initial_load_delete_stmt",
            "initial_load_batch_count",
            "ping_back_enabled",
            "last_update_by"
        ])->withTimestamps('create_time', 'last_update_time');
    }
}
