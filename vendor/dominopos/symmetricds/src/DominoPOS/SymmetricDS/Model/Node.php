<?php namespace DominoPOS\SymmetricDS\Model;

/**
 * Class Node
 * @property string node_id
 * @property string node_group_id
 * @property string external_id
 * @property string sync_enabled
 * @package DominoPOS\SymmetricDS
 */
class Node extends Base {
    protected $table = 'node';

    protected $primaryKey = 'node_id';

    public $timestamps = false;

    public function nodeGroup()
    {
        return $this->belongsTo('DominoPOS\\SymmetricDS\\Model\\NodeGroup', 'node_group_id', 'node_group_id');
    }
}
