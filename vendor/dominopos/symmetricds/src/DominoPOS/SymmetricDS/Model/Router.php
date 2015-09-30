<?php namespace DominoPOS\SymmetricDS\Model;

/**
 * Class Router
 * @package DominoPOS\SymmetricDS
 * @property string router_id
 * @property string target_catalog_name
 * @property string target_schema_name
 * @property string target_table_name
 * @property string source_node_group_id
 * @property string target_node_group_id
 * @property string router_type
 * @property string router_expression
 * @property string sync_on_update
 * @property string sync_on_insert
 * @property string sync_on_delete
 * @property string use_source_catalog_schema
 * @property string create_time
 * @property string last_update_by
 * @property string last_update_time
 */
class Router extends Base {
    protected $table = 'router';

    protected $primaryKey = 'router_id';

    public function sourceNode()
    {
        return $this->belongsTo(
            'DominoPOS\\SymmetricDS\\Model\\NodeGroup',
            'source_node_group_id',
            'node_group_id'
        );
    }

    public function targetNode()
    {
        return $this->belongsTo(
            'DominoPOS\\SymmetricDS\\Model\\NodeGroup',
            'target_node_group_id',
            'node_group_id'
        );
    }
}
