<?php namespace DominoPOS\SymmetricDS\Model;

/**
 * Class NodeSecurity
 * @property string node_id
 * @property string node_password
 * @property string registration_enabled
 * @property string registration_time
 * @property string initial_load_enabled
 * @property string initial_load_time
 * @property string initial_load_id
 * @property string initial_load_create_by
 * @property string rev_initial_load_enabled
 * @property string rev_initial_load_time
 * @property string rev_initial_load_id
 * @property string rev_initial_load_create_by
 * @property string created_at_node_id
 * @package DominoPOS\SymmetricDS
 */
class NodeSecurity extends Base {
    protected $table = 'node_security';

    protected $primaryKey = 'node_id';

    public $timestamps = false;
}
